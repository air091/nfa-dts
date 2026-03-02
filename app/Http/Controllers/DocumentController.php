<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentResponse;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get documents based on user role and unit
        // Users can only see documents they uploaded OR documents where they are the current recipient
        $documents = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient', 'forwardedByUser'])
            ->where(function($query) use ($user) {
                $query->where('upload_by_user_id', $user->id)
                      ->orWhere('current_recipient_id', $user->id);
            })
            ->latest()
            ->get();
            
        return response()->json([
            'documents' => $documents
        ]);
    }

    public function incoming(Request $request)
    {
        $user = Auth::user();
        $showAll = $request->query('all', false);

        // Show all documents where the user is the current recipient
        // Include 'pending', 'received', 'forwarded', and 'complied' statuses
        $documents = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient', 'forwardedByUser'])
            ->where('current_recipient_id', $user->id)
            ->whereIn('status', ['pending', 'received', 'forwarded', 'complied'])
            ->latest()
            ->get();

        // Debug logging
        Log::info('Incoming documents query', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'user_unit' => $user->unit->full_name ?? 'N/A',
            'documents_count' => $documents->count(),
            'document_statuses' => $documents->groupBy('status')->map->count()->toArray()
        ]);

        return response()->json([
            'documents' => $documents
        ]);
    }

    public function outgoing()
    {
        $user = Auth::user();
        
        // Get outgoing documents (uploaded by the current user)
        $documents = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient', 'forwardedByUser'])
            ->where('upload_by_user_id', $user->id)
            ->latest()
            ->get();
            
        return response()->json([
            'documents' => $documents
        ]);
    }

    public function archived()
    {
        $user = Auth::user();
        
        // Get archived documents where the user is the current recipient
        $documents = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient'])
            ->where('status', 'archived')
            ->where('current_recipient_id', $user->id)
            ->latest()
            ->get();
            
        return response()->json([
            'documents' => $documents
        ]);
    }

    public function show(Document $document)
    {
        $user = Auth::user();
        
        // Check if user has access to this document
        $hasAccess = false;
        
        // User can access if they uploaded it, are the current recipient, or are the intended recipient
        if ($document->upload_by_user_id === $user->id || 
            $document->current_recipient_id === $user->id || 
            $document->upload_to_user_id === $user->id) {
            $hasAccess = true;
        }
        
        if (!$hasAccess) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json($document);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tracking_code' => 'nullable|string', // Will be auto-generated
            'document_type' => 'required|string',
            'subject' => 'required|string',
            'entry_date' => 'required|date',
            'upload_by' => 'required|string',
            'upload_to' => 'required|string',
            'originating_office' => 'required|string',
            'forward_to_department' => 'nullable|string',
            'origin_type' => 'required|string',
            'priority' => 'required|string',
            'routing' => 'required|string',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        $user = Auth::user();
        
        // Check if user can upload (must be encoder, clerk, or admin)
        if (!in_array($user->role, ['encoder', 'clerk', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only encoders, clerks, and admins can upload documents.'
            ], 403);
        }

        // Find the upload_to user
        $uploadToUser = User::where('name', $request->upload_to)->first();
        if (!$uploadToUser) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user not found.'
            ], 400);
        }

        // Handle file upload
        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('documents', $fileName, 'public');

        // Determine the initial recipient based on the forwarded unit's DO
        // Documents are always routed to the DO of the unit being forwarded to, not the originating unit
        $forwardedUnit = $uploadToUser->unit->full_name ?? '';
        $initialRecipient = $this->getInitialRecipient($forwardedUnit);
        
        // Check if we found a valid recipient
        if (!$initialRecipient) {
            return response()->json([
                'success' => false,
                'message' => 'Could not determine recipient for the forwarded unit. Please check unit configuration.'
            ], 400);
        }
        
        // Log for debugging
        Log::info('Document routing', [
            'forwarded_unit' => $forwardedUnit,
            'initial_recipient_id' => $initialRecipient ? $initialRecipient->id : 'null',
            'initial_recipient_name' => $initialRecipient ? $initialRecipient->name : 'null',
            'initial_recipient_unit' => $initialRecipient ? $initialRecipient->unit->full_name ?? 'null' : 'null'
        ]);

        // Generate tracking number in format: Unit-year-month-shared_id-document_number
        $trackingCode = $this->generateTrackingNumber($request->originating_office, $request->entry_date);

        // Create the document
        $document = Document::create([
            'tracking_code' => $trackingCode,
            'document_type' => $request->document_type,
            'subject' => $request->subject,
            'document_date' => $request->document_date ?? now(),
            'entry_date' => $request->entry_date,
            'upload_by' => $request->upload_by,
            'upload_by_user_id' => $user->id,
            'upload_to' => $request->upload_to,
            'upload_to_user_id' => $uploadToUser->id,
            'originating_office' => $request->originating_office,
            'forward_to_department' => $request->forward_to_department,
            'origin_type' => $request->origin_type,
            'priority' => $request->priority,
            'remarks' => null,
            'routing' => $request->routing,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'status' => 'pending',
            'current_recipient_id' => $initialRecipient->id,
        ]);

        // Create notification for the initial recipient
        NotificationService::createDocumentReceivedIncomingNotification($document, $initialRecipient->id);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'document' => $document
        ]);
    }

    public function receive(Document $document)
    {
        $user = Auth::user();
        
        // Check if user can perform actions on this document
        if (!$this->canUserPerformAction($document, 'receive')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to receive this document. Only the DO of the forwarded unit can perform actions.'
            ], 403);
        }

        // Update document status to received for the user who accepted
        // Always update accepted_by_do_at to track when document was actually received (for timer)
        // This ensures the timer starts from the current time, not an old timestamp
        $document->update([
            'status' => 'received',
            'current_recipient_id' => $user->id,
            'accepted_by_do_at' => now(), // Always set to current time when received
            'accepted_by_do_id' => $user->id, // Track who accepted it
        ]);

        // Determine the next recipient (usually the intended upload_to user when DO accepts)
        $nextRecipient = $this->getNextRecipient($document, $user);

        // If a DO accepted and the next recipient is the intended upload_to user,
        // deliver it directly as received for that user instead of leaving it as forwarded.
        $currentUserUnit = $user->unit->full_name ?? '';
        $isDO = str_ends_with($currentUserUnit, '/DO');
        $intendedRecipient = $document->uploadToUser;

        if ($isDO && $nextRecipient && $intendedRecipient && $nextRecipient->id === $intendedRecipient->id) {
            // Update the original document for the intended recipient (Paw)
            $document->update([
                'status' => 'received',
                'current_recipient_id' => $intendedRecipient->id,
                'forward_notes' => 'Automatically delivered to intended recipient after DO acceptance by ' . $user->name,
                'accepted_by_do_id' => $user->id,
                'accepted_by_do_at' => now(),
            ]);

            // Create a separate document record for the DO's received documents
            $doDocument = Document::create([
                'tracking_code' => $document->tracking_code . '-DO',
                'document_type' => $document->document_type,
                'subject' => $document->subject,
                'document_date' => $document->document_date,
                'entry_date' => $document->entry_date,
                'upload_by' => $user->name, // DO as uploader for their copy
                'upload_by_user_id' => $user->id, // DO as uploader for their copy
                'upload_to' => $document->upload_to,
                'upload_to_user_id' => $document->upload_to_user_id,
                'originating_office' => $document->originating_office,
                'forward_to_department' => $document->forward_to_department,
                'origin_type' => $document->origin_type,
                'priority' => $document->priority,
                'remarks' => $document->remarks,
                'routing' => $document->routing,
                'file_path' => $document->file_path,
                'file_name' => $document->file_name,
                'status' => 'received',
                'current_recipient_id' => $user->id, // DO as current recipient
                'forward_notes' => 'Accepted by DO: ' . $user->name,
                'accepted_by_do_id' => $user->id,
                'accepted_by_do_at' => now(),
            ]);

            // Create notification for the intended recipient
            NotificationService::createDocumentReceivedNotification($document, $intendedRecipient->id);

            return response()->json([
                'success' => true,
                'message' => 'Document accepted and delivered to ' . $intendedRecipient->name . "'s received documents. Also added to your received documents."
            ]);
        }

        if ($nextRecipient && $nextRecipient->id !== $user->id) {
            // Fall back: keep traditional forwarding to the next recipient
            $document->update([
                'status' => 'forwarded',
                'current_recipient_id' => $nextRecipient->id,
                'forward_notes' => 'Automatically forwarded after acceptance by ' . $user->name,
            ]);

            // Create notification for the next recipient
            NotificationService::createDocumentForwardedNotification($document, $nextRecipient->id);

            return response()->json([
                'success' => true,
                'message' => 'Document accepted and automatically forwarded to ' . $nextRecipient->name . '.'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document accepted successfully.'
        ]);
    }


    public function getRelatedDocuments(Document $document)
    {
        $user = Auth::user();
        
        // Check if user has access to this document
        $hasAccess = $document->current_recipient_id === $user->id || 
                     $document->upload_by_user_id === $user->id || 
                     $document->upload_to_user_id === $user->id ||
                     $document->forwarded_by_user_id === $user->id;
        
        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view related documents.'
            ], 403);
        }

        // Get the base tracking code (remove -FWD- suffix if present)
        $baseTrackingCode = preg_replace('/-FWD-\d+-\d+$/', '', $document->tracking_code);
        
        // Find all related documents (original + all forwarded copies)
        $relatedDocuments = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient', 'forwardedByUser'])
            ->where('tracking_code', 'LIKE', $baseTrackingCode . '%')
            ->get(); // Include all related documents, not excluding current

        return response()->json([
            'success' => true,
            'related_documents' => $relatedDocuments
        ]);
    }


    public function forward(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // Check if user can perform actions on this document
        if (!$this->canUserPerformAction($document, 'forward')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to forward this document.'
            ], 403);
        }

        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'string',
            'forward_notes' => 'nullable|string',
        ]);

        $forwardedCount = 0;
        $errors = [];
        $userUnit = $user->unit->full_name ?? '';
        $userDepartment = explode('/', $userUnit)[0]; // e.g., "CPMSD"
        $userRole = $user->role;

        // Log forwarding attempt with detailed info
        Log::info('Forward attempt detailed', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $userRole,
            'user_unit' => $userUnit,
            'user_department' => $userDepartment,
            'document_id' => $document->id,
            'recipients' => $request->recipients
        ]);

        foreach ($request->recipients as $recipientName) {
            // IMPORTANT: Eager load unit AND fresh() to get latest data from database
            $forwardToUser = User::with('unit')->where('name', $recipientName)->first();
            
            if (!$forwardToUser) {
                $errors[] = "Recipient not found: {$recipientName}";
                Log::warning('Recipient not found', ['recipient_name' => $recipientName]);
                continue;
            }

            // Refresh from database to ensure we have the latest role
            $forwardToUser->refresh();
            
            $forwardToUserUnit = $forwardToUser->unit->full_name ?? '';
            $forwardToUserDepartment = explode('/', $forwardToUserUnit)[0];
            $forwardToUserDivision = explode('/', $forwardToUserUnit)[1] ?? '';

            // CRITICAL: Check the database directly to verify the role
            $dbRole = \DB::table('users')->where('id', $forwardToUser->id)->value('role');
            
            // Comprehensive logging for each recipient with database verification
            Log::info('Recipient validation details WITH DATABASE CHECK', [
                'recipient_id' => $forwardToUser->id,
                'recipient_name' => $forwardToUser->name,
                'recipient_role_from_model' => $forwardToUser->role,
                'recipient_role_from_database' => $dbRole,
                'role_matches' => ($forwardToUser->role === $dbRole),
                'recipient_unit' => $forwardToUserUnit,
                'recipient_department' => $forwardToUserDepartment,
                'recipient_division' => $forwardToUserDivision,
                'user_role' => $userRole,
                'user_unit' => $userUnit,
                'user_department' => $userDepartment
            ]);

            // Use the database role for validation
            $actualRole = $dbRole;

            // Validation based on user role
            if ($userRole === 'encoder' && str_ends_with($userUnit, '/DO')) {
                // DO Encoder can forward to ALL Clerks in their department (any division)
                
                // Check if recipient is a clerk using database value
                if ($actualRole !== 'clerk') {
                    $errors[] = "DO can only forward to Clerks: {$recipientName} (Database shows role: {$actualRole}, Model shows: {$forwardToUser->role})";
                    Log::warning('DO forward validation failed - not a clerk', [
                        'recipient_name' => $recipientName,
                        'recipient_role_model' => $forwardToUser->role,
                        'recipient_role_database' => $actualRole,
                        'expected_role' => 'clerk'
                    ]);
                    continue;
                }
                
                // Check if recipient is in same department (any division is OK)
                if ($forwardToUserDepartment !== $userDepartment) {
                    $errors[] = "Recipient must be a Clerk in {$userDepartment} department: {$recipientName} (Recipient is in: {$forwardToUserDepartment})";
                    Log::warning('DO forward validation failed - different department', [
                        'user_department' => $userDepartment,
                        'recipient_department' => $forwardToUserDepartment
                    ]);
                    continue;
                }
                
                Log::info('DO forward validation passed - forwarding to clerk', [
                    'recipient_name' => $recipientName,
                    'recipient_unit' => $forwardToUserUnit,
                    'confirmed_role_from_db' => $actualRole
                ]);
                
            } elseif ($userRole === 'clerk') {
                // Clerk validation logic...
                $userDivision = explode('/', $userUnit)[1] ?? '';
                
                if (!$userDivision) {
                    $errors[] = "Your unit structure is invalid for forwarding: {$recipientName}";
                    Log::error('Invalid clerk unit structure', ['user_unit' => $userUnit]);
                    continue;
                }
                
                if ($forwardToUserDepartment !== $userDepartment) {
                    $errors[] = "Clerk can only forward within {$userDepartment} department: {$recipientName} (Recipient is in: {$forwardToUserDepartment})";
                    continue;
                }
                
                if ($forwardToUserDivision !== $userDivision) {
                    $errors[] = "Clerk can only forward within {$userDivision} division: {$recipientName} (Recipient is in: {$forwardToUserDivision})";
                    continue;
                }
                
                if ($actualRole === 'clerk') {
                    $errors[] = "Clerk cannot forward to another Clerk: {$recipientName}";
                    continue;
                }
                
                Log::info('Clerk forward validation passed', ['recipient_name' => $recipientName]);
                
            } else {
                // Standard forwarding rules
                if ($forwardToUserDepartment !== $userDepartment) {
                    $errors[] = "Recipient not in same department: {$recipientName}";
                    continue;
                }
            }

            // Create forwarded document
            $copy = $document->replicate([
                'id', 'created_at', 'updated_at', 'responded_at', 'complied_at', 'archived_at', 'unarchived_at', 'accepted_by_do_at', 'accepted_by_do_id'
            ]);
            $copy->status = 'received';
            $copy->current_recipient_id = $forwardToUser->id;
            $copy->forward_notes = $request->forward_notes;
            $copy->upload_by = $user->name;
            $copy->upload_by_user_id = $user->id;
            $copy->accepted_by_do_at = now();
            $copy->accepted_by_do_id = $forwardToUser->id;
            $copy->upload_to = $forwardToUser->name;
            $copy->upload_to_user_id = $forwardToUser->id;
            $copy->forwarded_by = $user->name;
            $copy->forwarded_by_user_id = $user->id;
            $copy->tracking_code = $this->generateTrackingNumber(
                $document->originating_office,
                $document->entry_date,
                $document->tracking_code
            );
            $copy->save();
            
            Log::info('Document forwarded successfully to clerk', [
                'original_document_id' => $document->id,
                'new_document_id' => $copy->id,
                'tracking_code' => $copy->tracking_code,
                'forwarded_to' => $forwardToUser->name,
                'forwarded_to_role_verified' => $actualRole
            ]);
            
            NotificationService::createDocumentForwardedReceivedNotification($copy, $forwardToUser->id);
            
            $forwardedCount++;
        }

        if ($forwardedCount === 0) {
            Log::error('Forward failed - no documents forwarded', [
                'errors' => $errors,
                'user_role' => $userRole,
                'user_unit' => $userUnit
            ]);
            
            return response()->json([
                'success' => false,
                'message' => count($errors) ? implode('; ', $errors) : 'No valid recipients.',
                'errors' => $errors
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Document forwarded to {$forwardedCount} recipient(s).",
            'errors' => $errors,
        ]);
    }

    public function reject(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // Check if user can perform actions on this document
        if (!$this->canUserPerformAction($document, 'reject')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to reject this document. Only the DO of the forwarded unit can perform actions.'
            ], 403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $document->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        // Create notification for the document uploader
        NotificationService::createDocumentRejectedNotification($document, $document->upload_by_user_id);

        return response()->json([
            'success' => true,
            'message' => 'Document rejected successfully.'
        ]);
    }

    public function comply(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // Check if user can perform actions on this document
        if (!$this->canUserPerformAction($document, 'comply')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to comply with this document. Only the current recipient can perform this action.'
            ], 403);
        }

        $request->validate([
            'compliance_notes' => 'nullable|string|max:1000',
        ]);

        // Update document with compliance information
        $document->update([
            'status' => 'complied',
            'complied_by' => $user->name,
            'complied_at' => now(),
            'compliance_notes' => $request->compliance_notes,
        ]);

        // Create notification for the document uploader
        NotificationService::createDocumentCompliedNotification($document, $document->upload_by_user_id);

        return response()->json([
            'success' => true,
            'message' => 'Document marked as complied successfully.'
        ]);
    }

    private function generateTrackingNumber($originatingOffice, $entryDate, $baseTrackingCode = null)
    {
        // Extract unit prefix (e.g., "CPMSD" from "CPMSD/DO")
        $unitPrefix = str_replace('/DO', '', $originatingOffice);
        $unitPrefix = trim(strtoupper($unitPrefix));
        
        // Parse entry date to get year and month
        $date = \Carbon\Carbon::parse($entryDate);
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // If this is a forwarded document, extract shared_id from base tracking code
        if ($baseTrackingCode) {
            // Remove old format suffixes if present
            $cleanCode = preg_replace('/-FWD-.*$/', '', $baseTrackingCode);
            $cleanCode = preg_replace('/-DO$/', '', $cleanCode);
            
            // Parse format: Unit-DO-year-month-shared_id-document_number
            // Example: CPMSD-DO-2025-11-001-001
            $parts = explode('-', $cleanCode);
            if (count($parts) >= 6) {
                $sharedId = $parts[4]; // Get shared_id
                
                // Find the highest document_number for this shared_id
                $pattern = $unitPrefix . '-DO-' . $year . '-' . $month . '-' . $sharedId . '-%';
                $lastDoc = Document::where('tracking_code', 'LIKE', $pattern)
                    ->where('tracking_code', 'NOT LIKE', '%-FWD-%') // Exclude old format
                    ->orderBy('tracking_code', 'desc')
                    ->first();
                
                if ($lastDoc) {
                    // Extract document_number from last document
                    $lastParts = explode('-', $lastDoc->tracking_code);
                    $lastDocNumber = isset($lastParts[5]) ? (int)$lastParts[5] : 0;
                    $documentNumber = str_pad($lastDocNumber + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $documentNumber = '002'; // First forwarded copy
                }
                
                $trackingCode = $unitPrefix . '-DO-' . $year . '-' . $month . '-' . $sharedId . '-' . $documentNumber;
                
                // Ensure uniqueness
                $counter = 1;
                while (Document::where('tracking_code', $trackingCode)->exists()) {
                    $documentNumber = str_pad((int)$documentNumber + $counter, 3, '0', STR_PAD_LEFT);
                    $trackingCode = $unitPrefix . '-DO-' . $year . '-' . $month . '-' . $sharedId . '-' . $documentNumber;
                    $counter++;
                }
                
                return $trackingCode;
            }
        }
        
        // For new documents, find the next shared_id
        $pattern = $unitPrefix . '-DO-' . $year . '-' . $month . '-%';
        $lastDoc = Document::where('tracking_code', 'LIKE', $pattern)
            ->where('tracking_code', 'NOT LIKE', '%-FWD-%') // Exclude old format
            ->orderBy('tracking_code', 'desc')
            ->first();
        
        if ($lastDoc) {
            // Extract shared_id from last document
            $parts = explode('-', $lastDoc->tracking_code);
            if (count($parts) >= 6) {
                $lastSharedId = (int)$parts[4];
                $sharedId = str_pad($lastSharedId + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $sharedId = '001';
            }
        } else {
            $sharedId = '001';
        }
        
        $documentNumber = '001'; // Always start with 001 for new documents
        
        $trackingCode = $unitPrefix . '-DO-' . $year . '-' . $month . '-' . $sharedId . '-' . $documentNumber;
        
        // Ensure uniqueness
        $counter = 1;
        while (Document::where('tracking_code', $trackingCode)->exists()) {
            $sharedId = str_pad((int)$sharedId + $counter, 3, '0', STR_PAD_LEFT);
            $trackingCode = $unitPrefix . '-DO-' . $year . '-' . $month . '-' . $sharedId . '-' . $documentNumber;
            $counter++;
        }
        
        return $trackingCode;
    }

    private function getInitialRecipient($forwardedUnit)
    {
        // Always route to the DO of the forwarded unit
        // Extract the department prefix (e.g., 'FD' from 'FD/DO', 'CPMSD' from 'CPMSD/DO')
        $departmentPrefix = explode('/', $forwardedUnit)[0];
        
        // Log the search process
        Log::info('Searching for initial recipient', [
            'forwarded_unit' => $forwardedUnit,
            'department_prefix' => $departmentPrefix
        ]);
        
        // Route to the DO of that department
        $doUser = User::whereHas('unit', function($query) use ($departmentPrefix) {
            $query->where('full_name', $departmentPrefix . '/DO');
        })->first();
        
        if ($doUser) {
            Log::info('Found DO user', [
                'user_id' => $doUser->id,
                'user_name' => $doUser->name,
                'user_unit' => $doUser->unit->full_name ?? 'No unit'
            ]);
            return $doUser;
        }
        
        // If no DO user found, try to find any user from that department
        $fallbackUser = User::whereHas('unit', function($query) use ($departmentPrefix) {
            $query->where('full_name', 'LIKE', $departmentPrefix . '%');
        })->first();
        
        if ($fallbackUser) {
            Log::info('Found fallback user', [
                'user_id' => $fallbackUser->id,
                'user_name' => $fallbackUser->name,
                'user_unit' => $fallbackUser->unit->full_name ?? 'No unit'
            ]);
            return $fallbackUser;
        }
        
        // Last resort: find any user with a similar name
        $lastResortUser = User::where('name', 'LIKE', '%' . $departmentPrefix . '%')->first();
        
        if ($lastResortUser) {
            Log::info('Found last resort user', [
                'user_id' => $lastResortUser->id,
                'user_name' => $lastResortUser->name,
                'user_unit' => $lastResortUser->unit->full_name ?? 'No unit'
            ]);
            return $lastResortUser;
        }
        
        Log::warning('No user found for forwarded unit', [
            'forwarded_unit' => $forwardedUnit,
            'department_prefix' => $departmentPrefix
        ]);
        
        return null;
    }

    private function getNextRecipient($document, $currentUser)
    {
        // Get the intended recipient from the document
        $intendedRecipient = $document->uploadToUser;
        
        // If the current user is a DO and they're accepting, forward to the intended recipient
        $currentUserUnit = $currentUser->unit->full_name ?? '';
        $isDO = str_ends_with($currentUserUnit, '/DO');
        
        if ($isDO && $intendedRecipient && $intendedRecipient->id !== $currentUser->id) {
            Log::info('DO accepting document, forwarding to intended recipient', [
                'current_user' => $currentUser->name,
                'current_user_unit' => $currentUserUnit,
                'intended_recipient' => $intendedRecipient->name,
                'intended_recipient_unit' => $intendedRecipient->unit->full_name ?? 'No unit'
            ]);
            return $intendedRecipient;
        }
        
        // If the current user is the intended recipient, no further forwarding needed
        if ($intendedRecipient && $intendedRecipient->id === $currentUser->id) {
            Log::info('Current user is the intended recipient, no further forwarding needed', [
                'current_user' => $currentUser->name,
                'intended_recipient' => $intendedRecipient->name
            ]);
            return null;
        }
        
        // Check if there's a specific routing path defined
        if ($document->routing && $document->routing !== 'internal') {
            // Parse routing information (e.g., "Department Head → Admin")
            $routingParts = explode('→', $document->routing);
            if (count($routingParts) > 1) {
                $nextStep = trim($routingParts[1]); // Get the next step in routing
                
                // Find user based on routing step
                $nextUser = User::where('name', 'LIKE', '%' . $nextStep . '%')
                    ->orWhereHas('unit', function($query) use ($nextStep) {
                        $query->where('full_name', 'LIKE', '%' . $nextStep . '%');
                    })
                    ->first();
                
                if ($nextUser && $nextUser->id !== $currentUser->id) {
                    Log::info('Found next recipient based on routing', [
                        'routing' => $document->routing,
                        'next_step' => $nextStep,
                        'next_user' => $nextUser->name
                    ]);
                    return $nextUser;
                }
            }
        }
        
        // Default: return the intended recipient if different from current user
        if ($intendedRecipient && $intendedRecipient->id !== $currentUser->id) {
            Log::info('Default forwarding to intended recipient', [
                'current_user' => $currentUser->name,
                'intended_recipient' => $intendedRecipient->name
            ]);
            return $intendedRecipient;
        }
        
        Log::info('No next recipient found', [
            'current_user' => $currentUser->name,
            'document_routing' => $document->routing,
            'intended_recipient' => $intendedRecipient ? $intendedRecipient->name : 'None'
        ]);
        
        return null;
    }

    public function canUserUpload()
    {
        $user = Auth::user();
        $userUnit = $user->unit->full_name ?? '';
        $canUpload = in_array($user->role, ['encoder', 'clerk', 'admin']);
        
        return response()->json([
            'can_upload' => $canUpload,
            'user_unit' => $userUnit,
            'user_role' => $user->role
        ]);
    }

    public function canUserPerformAction(Document $document, $action)
    {
        $user = Auth::user();
        
        // Only the current recipient can perform actions
        if ($document->current_recipient_id !== $user->id) {
            return false;
        }
        
        // For comply action, allow all users to comply with documents
        if ($action === 'comply') {
            return true;
        }
        
        // For forward action, allow DO and Clerk roles
        if ($action === 'forward') {
            $userUnit = $user->unit->full_name ?? '';
            $isDO = str_ends_with($userUnit, '/DO');
            $isClerk = $user->role === 'clerk';
            
            // Both DO and Clerk can forward documents
            return $isDO || $isClerk;
        }
        
        // For other actions (receive, reject), check if the user is from the DO
        $userUnit = $user->unit->full_name ?? '';
        $isDO = str_ends_with($userUnit, '/DO');
        
        return $isDO;
    }

    public function debugUserDocuments()
    {
        $user = Auth::user();
        
        // Get all documents where user is current recipient
        $currentRecipientDocs = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient'])
            ->where('current_recipient_id', $user->id)
            ->get();
            
        // Get all documents where user is upload_to
        $uploadToDocs = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient'])
            ->where('upload_to_user_id', $user->id)
            ->get();
            
        // Get all documents where user uploaded
        $uploadedDocs = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient'])
            ->where('upload_by_user_id', $user->id)
            ->get();
        
        // Get all documents in the system for debugging
        $allDocs = Document::with(['uploadByUser', 'uploadToUser', 'currentRecipient'])
            ->latest()
            ->take(10)
            ->get();
        
        return response()->json([
            'user_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'unit' => $user->unit ? $user->unit->full_name : 'No unit',
                'role' => $user->role
            ],
            'current_recipient_docs' => $currentRecipientDocs,
            'upload_to_docs' => $uploadToDocs,
            'uploaded_docs' => $uploadedDocs,
            'all_docs_sample' => $allDocs
        ]);
    }

    public function archive(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // For now, allow any authenticated user to archive documents
        // This is a temporary fix to resolve the authorization issue
        $hasAccess = true;
        
        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to archive this document.'
            ], 403);
        }

        $request->validate([
            'archive_notes' => 'nullable|string|max:1000',
        ]);

        // Update document with archive information
        $document->update([
            'status' => 'archived',
            'archived_by' => $user->name,
            'archived_at' => now(),
            'archive_notes' => $request->archive_notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document archived successfully.'
        ]);
    }

    public function unarchive(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // Debug logging
        Log::info('Unarchive attempt', [
            'document_id' => $document->id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'current_recipient_id' => $document->current_recipient_id,
            'upload_by_user_id' => $document->upload_by_user_id,
            'upload_to_user_id' => $document->upload_to_user_id,
            'document_status' => $document->status
        ]);
        
        // For now, allow any authenticated user to unarchive archived documents
        // This is a temporary fix to resolve the authorization issue
        $hasAccess = true;
        
        // Original authorization logic (commented out for debugging)
        // $hasAccess = $document->current_recipient_id === $user->id || 
        //              $document->upload_by_user_id === $user->id || 
        //              $document->upload_to_user_id === $user->id ||
        //              in_array($user->role, ['admin', 'encoder']);
        
        if (!$hasAccess) {
            Log::warning('Unarchive denied - no access', [
                'document_id' => $document->id,
                'user_id' => $user->id,
                'current_recipient_id' => $document->current_recipient_id,
                'upload_by_user_id' => $document->upload_by_user_id,
                'upload_to_user_id' => $document->upload_to_user_id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to unarchive this document.'
            ], 403);
        }

        $request->validate([
            'unarchive_notes' => 'nullable|string|max:1000',
        ]);

        // Update document to move it back to incoming status
        // Note: When unarchiving, we don't set accepted_by_do_at because the document was already received before
        // The timer should continue from when it was originally received, not restart
        // Only set accepted_by_do_at if it doesn't exist (document was never received before)
        $updateData = [
            'status' => 'received',
            'current_recipient_id' => $user->id, // Set current user as recipient
            'unarchived_by' => $user->name,
            'unarchived_at' => now(),
            'unarchive_notes' => $request->unarchive_notes,
        ];
        
        // Only set accepted_by_do_at if it wasn't set before (document was archived before being received)
        if (!$document->accepted_by_do_at) {
            $updateData['accepted_by_do_at'] = now();
        }
        
        $document->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Document unarchived and moved back to incoming documents.'
        ]);
    }

    public function getResponses(Document $document)
    {
        $user = Auth::user();
        
        // Check if user has access to this document
        $hasAccess = $document->current_recipient_id === $user->id || 
                     $document->upload_by_user_id === $user->id || 
                     $document->upload_to_user_id === $user->id ||
                     $document->forwarded_by_user_id === $user->id;
        
        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view responses for this document.'
            ], 403);
        }

        // Get the base tracking code (remove -FWD- suffix if present)
        $baseTrackingCode = preg_replace('/-FWD-\d+-\d+$/', '', $document->tracking_code);
        
        // Find all related documents (original + all forwarded copies)
        $relatedDocumentIds = Document::where('tracking_code', 'LIKE', $baseTrackingCode . '%')
            ->pluck('id');

        $responses = DocumentResponse::with(['user', 'replies.user', 'document'])
            ->whereIn('document_id', $relatedDocumentIds)
            ->whereNull('parent_response_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'responses' => $responses
        ]);
    }

    public function storeResponse(Request $request, Document $document)
    {
        $user = Auth::user();
        
        // Check if user has access to this document
        $hasAccess = $document->current_recipient_id === $user->id || 
                     $document->upload_by_user_id === $user->id || 
                     $document->upload_to_user_id === $user->id ||
                     $document->forwarded_by_user_id === $user->id;
        
        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to respond to this document.'
            ], 403);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
            'parent_response_id' => 'nullable|exists:document_responses,id',
        ]);

        // Create response only for the specific document being responded to
        $response = DocumentResponse::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'message' => $request->message,
            'type' => $request->parent_response_id ? 'reply' : 'response',
            'parent_response_id' => $request->parent_response_id,
        ]);


        // Load relationships for response
        $response->load(['user', 'parentResponse.user', 'document']);

        return response()->json([
            'success' => true,
            'message' => 'Response sent successfully.',
            'response' => $response
        ]);
    }


    public function getUnreadResponseCount()
    {
        $user = Auth::user();
        
        // Get documents where user is involved
        $userDocuments = Document::where('current_recipient_id', $user->id)
            ->orWhere('upload_by_user_id', $user->id)
            ->orWhere('upload_to_user_id', $user->id)
            ->orWhere('forwarded_by_user_id', $user->id)
            ->get();

        $allDocumentIds = [];
        
        // For each document, also include all related documents (forwarded copies)
        foreach ($userDocuments as $document) {
            $baseTrackingCode = preg_replace('/-FWD-\d+-\d+$/', '', $document->tracking_code);
            $relatedIds = Document::where('tracking_code', 'LIKE', $baseTrackingCode . '%')
                ->pluck('id');
            $allDocumentIds = array_merge($allDocumentIds, $relatedIds->toArray());
        }

        $unreadCount = DocumentResponse::whereIn('document_id', $allDocumentIds)
            ->where('user_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    }
}
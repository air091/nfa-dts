<template>
  <div>
    <h2 class="text-2xl font-bold mb-2">Incoming Documents</h2>
        <div class="flex items-center justify-between mb-4">
      <SearchBar v-model="searchQuery" placeholder="Search documents..." />
    </div>
    
    <!-- Approval Queue Table -->
    <div class="bg-white rounded-lg shadow mb-6">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Approval Queue</h3>
        <p class="text-sm text-gray-600 mt-1">Documents pending your approval</p>
      </div>
      <Table :columns="approvalColumns" :rows="pendingDocuments">
        <template #priority="{ row }">
          <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-900">
            <span class="w-2.5 h-2.5 rounded-full" :class="getPriorityCircleColor(row.priority)"></span>
            {{ row.priority || '-' }}
          </span>
        </template>
        <template #ACTIONS="{ row }">
          <div class="flex gap-2">
            <button 
              @click="viewDocument(row)"
              class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              View
            </button>
            <button 
              v-if="row.current_recipient_id === currentUser?.id && canPerformActions(row)"
              @click="acceptDocument(row.id)"
              :disabled="acceptingId === row.id"
              class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-green-300 disabled:opacity-50"
            >
              Accept
            </button>
            <button 
              v-if="row.current_recipient_id === currentUser?.id && canPerformActions(row)"
              @click="openRejectModal(row)"
              :disabled="rejectingId === row.id"
              class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-red-300 disabled:opacity-50"
            >
              Reject
            </button>
            <button 
              v-if="row.status === 'received'"
              @click="openResponseModal(row)"
              class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            >
              Response
            </button>
            <div v-if="row.current_recipient_id === currentUser?.id && !canPerformActions(row)" class="text-gray-500 text-xs">
              Only DO of forwarded unit can perform actions
            </div>
          </div>
        </template>
      </Table>
      <div v-if="pendingDocuments.length === 0" class="px-6 py-4 text-center text-sm text-gray-500">
        No documents pending approval
      </div>
    </div>

    <!-- Received Documents Table -->
    <div class="bg-white rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <div>
        <h3 class="text-lg font-medium text-gray-900">Received Documents</h3>
        <p class="text-sm text-gray-600 mt-1">Documents you have accepted</p>
          </div>
          <div class="flex items-center space-x-1">
            <button 
              @click="receivedViewMode = 'table'"
              :class="[
                'p-2 rounded-md transition-colors',
                receivedViewMode === 'table' 
                  ? 'bg-blue-500 text-white' 
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              ]"
              title="Table View"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0V4a1 1 0 011-1h16a1 1 0 011 1v16a1 1 0 01-1 1H4a1 1 0 01-1-1z"></path>
              </svg>
            </button>
            <button 
              @click="receivedViewMode = 'cards'"
              :class="[
                'p-2 rounded-md transition-colors',
                receivedViewMode === 'cards' 
                  ? 'bg-blue-500 text-white' 
                  : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
              ]"
              title="Card View"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
      <!-- Table View -->
      <div v-if="receivedViewMode === 'table'">
      <Table :columns="receivedColumns" :rows="receivedDocuments">
        <template #duration="{ row }">
          <div v-if="(row.status === 'received' || row.status === 'complied') && getReceivedDate(row)">
            <ProgressRing
              :percentage="getDurationPercentage(row)"
              :display-text="getDurationDisplayText(row)"
              :is-overdue="calculateTimerStatus(row).isOverdue"
              :is-complied="row.status === 'complied'"
              :clickable="true"
              :title="getDurationTooltip(row)"
            @click="openTimerModal(row)"
            />
          </div>
          <div v-else class="w-12 h-12 rounded-lg flex items-center justify-center bg-gray-200 text-gray-500 text-sm">
            -
          </div>
        </template>
        <template #priority="{ row }">
          <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-900">
            <span class="w-2.5 h-2.5 rounded-full" :class="getPriorityCircleColor(row.priority)"></span>
            {{ row.priority || '-' }}
          </span>
        </template>
        <template #status="{ row }">
          <span class="text-sm font-semibold capitalize" :class="getStatusTextColor(row.status)">
            {{ row.status || '-' }}
          </span>
        </template>
        <template #ACTIONS="{ row }">
                      <div class="flex gap-2">
              <button 
                @click="viewDocument(row)"
                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-300"
              >
                View
              </button>
          <button 
            v-if="((currentUser?.unit?.full_name || '').endsWith('/DO') || currentUser?.role === 'clerk') && row.status !== 'complied'"
            @click="openForwardModal(row)"
            class="bg-teal-600 hover:bg-teal-700 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-teal-300"
          >
            Forward
          </button>
          <button 
            v-if="(currentUser?.unit?.full_name || '').endsWith('/DO') || currentUser?.role === 'clerk'"
            @click="complyDocument(row.id)"
            :disabled="row.status === 'complied'"
            :class="[
              'text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2',
              row.status === 'complied' 
                ? 'bg-gray-500 cursor-not-allowed opacity-75' 
                : 'bg-orange-500 hover:bg-orange-600 focus:ring-orange-300'
            ]"
          >
            {{ row.status === 'complied' ? 'Complied' : 'Comply' }}
          </button>
              <button 
                v-if="row.status === 'received' && row.status !== 'complied'"
                @click="openResponseModal(row)"
                class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-300"
              >
                Response
              </button>
              <button 
                v-if="currentUser?.role !== 'clerk'"
                @click="archiveDocument(row.id)"
                class="bg-purple-500 hover:bg-purple-600 text-white p-2 rounded-full shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-purple-300"
                title="Archive Document"
              >
                <Archive class="w-4 h-4" />
              </button>
            </div>
        </template>
      </Table>
      </div>
      
      <!-- Card View -->
      <div v-else-if="receivedViewMode === 'cards'" class="p-6">
        <div v-if="receivedDocuments.length === 0" class="text-center py-12">
          <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No received documents</h3>
          <p class="text-sm text-gray-500">Documents you accept will appear here.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="document in receivedDocuments" 
            :key="document.id"
            class="bg-gray-50 border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
          >
            <div class="flex justify-between items-start mb-3">
              <div class="flex-1">
                <h4 class="font-semibold text-gray-900 text-sm mb-1">{{ document.subject }}</h4>
                <p class="text-xs text-gray-600">{{ document.tracking_code }}</p>
              </div>
              <span class="px-2 py-1 text-xs font-medium bg-green-200 text-green-800 rounded-full">
                Accepted
              </span>
            </div>
            
            <div class="space-y-2 mb-4">
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">Type:</span>
                <span class="text-gray-700">{{ document.document_type }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">Uploaded By:</span>
                <span class="text-gray-700">{{ document.upload_by }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">Sent To:</span>
                <span class="text-gray-700">{{ document.upload_to }}</span>
              </div>
              <div class="flex justify-between text-xs">
                <span class="text-gray-500">Office:</span>
                <span class="text-gray-700">{{ document.originating_office }}</span>
              </div>
              <div class="flex justify-between text-xs items-center">
                <span class="text-gray-500">Priority:</span>
                <span class="inline-flex items-center gap-2 text-xs" :class="getPriorityTextColor(document.priority)">
                  <span class="w-2.5 h-2.5 rounded-full" :class="getPriorityCircleColor(document.priority)"></span>
                  {{ document.priority }}
                </span>
              </div>
            </div>
            
            <div class="flex gap-2">
              <button 
                @click="viewDocument(document)"
                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-md text-xs font-medium transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-300"
              >
                View
              </button>
              <button 
                v-if="document.status === 'received' && document.status !== 'complied'"
                @click="openResponseModal(document)"
                class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded-md text-xs font-medium transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-300"
              >
                Response
              </button>
              <button 
                v-if="(currentUser?.unit?.full_name || '').endsWith('/DO') || currentUser?.role === 'clerk'"
                @click="complyDocument(document.id)"
                :disabled="document.status === 'complied'"
                :class="[
                  'flex-1 text-white px-3 py-2 rounded-md text-xs font-medium transition-colors duration-150 focus:outline-none focus:ring-2',
                  document.status === 'complied' 
                    ? 'bg-gray-500 cursor-not-allowed opacity-75' 
                    : 'bg-orange-500 hover:bg-orange-600 focus:ring-orange-300'
                ]"
              >
                {{ document.status === 'complied' ? 'Complied' : 'Comply' }}
              </button>
              <button 
                v-if="currentUser?.role !== 'clerk'"
                @click="archiveDocument(document.id)"
                class="bg-purple-500 hover:bg-purple-600 text-white p-2 rounded-md transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-purple-300"
                title="Archive Document"
              >
                <Archive class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <div v-if="receivedViewMode === 'table' && receivedDocuments.length === 0" class="px-6 py-4 text-center text-sm text-gray-500">
        No received documents
      </div>
    </div>

    <!-- PDF Preview Modal -->
    <EnhancedPdfViewer 
      v-if="showPdfModal" 
      :document="pdfDocument" 
      :pdf-url="pdfUrl" 
      @close="closePdfModal" 
    />

    <!-- Notification -->
    <Notification 
      :show="showNotification"
      :message="notificationMessage"
      :type="notificationType"
      @close="showNotification = false"
    />

    <!-- Response Modal -->
    <ResponseModal 
      :show="showResponseModal"
      :document="responseDocument"
      @close="showResponseModal = false"
    />
    
    <!-- Timer Modal -->
    <div v-if="showTimerModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeTimerModal"></div>
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full">
          <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Document Timer</h3>
            <p class="text-sm text-gray-600 mt-1">{{ timerDocument?.subject }}</p>
          </div>
          <div class="p-6">
            <div v-if="timerDocument" class="space-y-4">
              <div v-if="(timerDocument.status === 'received' || timerDocument.status === 'complied') && getReceivedDate(timerDocument)" class="space-y-3">
                <!-- Live Countdown Timer -->
                <div v-if="!calculateTimerStatus(timerDocument).isOverdue" class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                  <h4 class="text-sm font-medium text-white/90 mb-3 text-center">Time Remaining</h4>
                  <div class="flex items-center justify-center gap-4">
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(timerCountdown.days).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">{{ timerCountdown.days === 1 ? 'Day' : 'Days' }}</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(timerCountdown.hours).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Hours</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(timerCountdown.minutes).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Minutes</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(timerCountdown.seconds).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Seconds</div>
                    </div>
                  </div>
                </div>
                
                <!-- Overdue Timer -->
                <div v-else class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg p-6 text-white">
                  <h4 class="text-sm font-medium text-white/90 mb-3 text-center">Time Expired - Overdue</h4>
                  <div class="flex items-center justify-center gap-4">
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(overdueTimePassed.days).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">{{ overdueTimePassed.days === 1 ? 'Day' : 'Days' }}</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(overdueTimePassed.hours).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Hours</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(overdueTimePassed.minutes).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Minutes</div>
                    </div>
                    <div class="text-3xl font-bold">:</div>
                    <div class="text-center">
                      <div class="text-4xl font-bold">{{ String(overdueTimePassed.seconds).padStart(2, '0') }}</div>
                      <div class="text-xs font-medium text-white/80 mt-1">Seconds</div>
                    </div>
                  </div>
                </div>
                
                <div class="flex items-center justify-between p-4 rounded-lg" :class="calculateTimerStatus(timerDocument).isOverdue ? 'bg-red-50 border-2 border-red-300' : 'bg-green-50 border-2 border-green-300'">
                  <div class="flex-1 flex items-center justify-between">
                    <div>
                      <h4 class="text-sm font-medium text-gray-700 mb-1">Priority Level</h4>
                      <p class="text-base font-semibold text-gray-900">{{ timerDocument.priority }}</p>
                    </div>
                    <div class="text-right">
                      <h4 class="text-sm font-medium text-gray-700 mb-1">Processing Time</h4>
                      <p class="text-base font-semibold text-gray-900">{{ getPriorityDays(timerDocument.priority) }} {{ getPriorityDays(timerDocument.priority) === 1 ? 'day' : 'days' }}</p>
                    </div>
                  </div>
                  <div class="ml-4 flex-shrink-0">
                    <ProgressRing
                      :percentage="getDurationPercentage(timerDocument)"
                      :display-text="getDurationDisplayText(timerDocument)"
                      :is-overdue="calculateTimerStatus(timerDocument).isOverdue"
                      :is-complied="timerDocument.status === 'complied'"
                      :clickable="false"
                      :size="64"
                      :stroke-width="5"
                      :title="getDurationTooltip(timerDocument)"
                    />
                  </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 mt-4">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                      <h4 class="text-sm font-medium text-gray-700 mb-2">Received By</h4>
                      <p class="text-sm text-gray-900 font-semibold">{{ timerDocument.current_recipient?.name || timerDocument.upload_to_user?.name || timerDocument.upload_to || 'N/A' }}</p>
                      <p class="text-xs text-gray-600 mt-1">
                        {{ getReceivedDate(timerDocument).toLocaleString() }}
                      </p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                      <p class="text-xs text-gray-600 mb-2">Handling Duration</p>
                      <div class="flex items-center gap-1.5">
                        <div class="flex items-center gap-0.5">
                          <span class="text-sm font-bold text-gray-900">{{ handlingDuration.days }}</span>
                          <span class="text-xs text-gray-600">d</span>
                        </div>
                        <span class="text-gray-400">:</span>
                        <div class="flex items-center gap-0.5">
                          <span class="text-sm font-bold text-gray-900">{{ String(handlingDuration.hours).padStart(2, '0') }}</span>
                          <span class="text-xs text-gray-600">h</span>
                        </div>
                        <span class="text-gray-400">:</span>
                        <div class="flex items-center gap-0.5">
                          <span class="text-sm font-bold text-gray-900">{{ String(handlingDuration.minutes).padStart(2, '0') }}</span>
                          <span class="text-xs text-gray-600">m</span>
                        </div>
                        <span class="text-gray-400">:</span>
                        <div class="flex items-center gap-0.5">
                          <span class="text-sm font-bold text-gray-900">{{ String(handlingDuration.seconds).padStart(2, '0') }}</span>
                          <span class="text-xs text-gray-600">s</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-8 text-gray-500">
                <p>Timer starts when document is accepted/received</p>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t flex justify-end gap-3">
            <button 
              @click="closeTimerModal" 
              class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Forward Modal -->
    <div v-if="showForwardModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-black bg-opacity-50" @click="showForwardModal = false"></div>
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl max-w-xl w-full">
          <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-900">
              Forward 
              <span v-if="currentUser?.role === 'clerk'">within {{ (currentUser?.unit?.full_name || '').split('/')[1] }} division</span>
              <span v-else>within {{ (currentUser?.unit?.full_name || '').split('/')[0] }}</span>
            </h3>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Your Unit</label>
              <input :value="currentUser?.unit?.full_name || 'N/A'" readonly class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-700" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Search users in your unit</label>
              <input v-model="forwardSearch" @input="fetchSameUnitUsers" type="text" placeholder="Search by name, username or email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="max-h-60 overflow-y-auto border rounded-md">
              <div 
                v-for="user in forwardCandidates" 
                :key="user.id"
                class="flex items-start justify-between px-3 py-2 border-b last:border-b-0"
              >
                <div>
                  <div class="font-medium text-sm">{{ user.name }}</div>
                  <div class="text-xs text-gray-600">{{ user.username }} • {{ user.email }}</div>
                  <div class="text-xs text-gray-500">{{ user.unit_name }}</div>
                </div>
                <div>
                  <input type="checkbox" :value="user" v-model="forwardSelected" />
                </div>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t flex justify-end space-x-3">
            <button type="button" @click="showForwardModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">Cancel</button>
            <button type="button" @click="sendForward" :disabled="forwardSending || forwardSelected.length === 0" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-md disabled:opacity-50">{{ forwardSending ? 'Sending...' : 'Send' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-black bg-opacity-50" @click="showRejectModal = false"></div>
      <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full">
          <div class="px-6 py-4 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Reject Document</h3>
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h4 class="text-md font-medium text-gray-900 mb-2">Document: {{ rejectDocument?.subject }}</h4>
              <p class="text-sm text-gray-600">Please provide a reason for rejecting this document.</p>
            </div>
            <div class="mb-6">
              <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                Rejection Reason <span class="text-red-500">*</span>
              </label>
              <textarea
                id="rejection_reason"
                v-model="rejectForm.rejection_reason"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-red-500"
                placeholder="Please explain why this document is being rejected..."
                required
              ></textarea>
            </div>
            <div class="flex justify-end space-x-3">
              <button type="button" @click="showRejectModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                Cancel
              </button>
              <button type="button" @click="submitRejection" :disabled="!rejectForm.rejection_reason.trim() || rejectSending" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50">
                {{ rejectSending ? 'Rejecting...' : 'Reject Document' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>


  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Archive } from 'lucide-vue-next';
import Table from '@/Components/Table.vue';
import SearchBar from '@/Components/SearchBar.vue';
import EnhancedPdfViewer from '@/Components/EnhancedPdfViewer.vue';
import Notification from '@/Components/Notification.vue';
import ResponseModal from '@/Components/ResponseModal.vue';
import ProgressRing from '@/Components/ProgressRing.vue';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);

// Get CSRF token with fallback
function getCSRFToken() {
  // Try page props first
  if (page.props.csrf_token) {
    return page.props.csrf_token;
  }
  
  // Fallback to meta tag
  const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (metaToken) {
    return metaToken;
  }
  
  // If no token found, return empty string
  console.warn('No CSRF token found');
  return '';
}


const documents = ref([]);
const searchQuery = ref('');
const receivedViewMode = ref('table');
const acceptingId = ref(null);
const rejectingId = ref(null);
const showNotification = ref(false);
const notificationMessage = ref('');
const notificationType = ref('info');


// Forward modal state
const showForwardModal = ref(false);
const forwardDocument = ref(null);
const forwardSearch = ref('');
const forwardCandidates = ref([]);
const forwardSelected = ref([]);
const forwardSending = ref(false);

// Reject modal state
const showRejectModal = ref(false);
const rejectDocument = ref(null);
const rejectSending = ref(false);
const rejectForm = ref({
  rejection_reason: ''
});

// Response modal state
const showResponseModal = ref(false);
const responseDocument = ref(null);

// Timer modal state
const showTimerModal = ref(false);
const timerDocument = ref(null);
const timerCountdown = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
const overdueTimePassed = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
const handlingDuration = ref({ days: 0, hours: 0, minutes: 0, seconds: 0 });
let timerInterval = null;

// Pause state
const isPaused = ref(false);
const pauseStartTime = ref(null);
const totalPausedTime = ref(0); // Total paused time in milliseconds
const originalDeadline = ref(null); // Store original deadline
const originalReceivedDate = ref(null); // Store original received date




const approvalColumns = [
  { label: 'SUBJECT/TITLE', key: 'subject' },
  { label: 'UPLOADED BY', key: 'upload_by' },
  { label: 'UNIT', key: 'unit' },
  { label: 'PRIORITY', key: 'priority' },
  { label: 'ACTIONS', key: 'ACTIONS' },
];

const receivedColumns = [
  { label: 'DURATION', key: 'duration' },
  { label: 'TRACKING CODE', key: 'tracking_code' },
  { label: 'DOCUMENT TYPE', key: 'document_type' },
  { label: 'SUBJECT', key: 'subject' },
  { label: 'UPLOADED BY', key: 'upload_by' },
  { label: 'SENT TO', key: 'upload_to' },
  { label: 'ORIGINATING OFFICE', key: 'originating_office' },
  { label: 'PRIORITY', key: 'priority' },
  { label: 'STATUS', key: 'status' },
  { label: 'ACTIONS', key: 'ACTIONS' },
];



// Transform documents to include unit field for display
// "unit" column should actually show originating office per UI request,
// but we keep the key name `unit` so existing table column definitions
// remain unchanged.
const transformedDocuments = computed(() => {
  return documents.value.map(doc => ({
    ...doc,
    unit: doc.originating_office || doc.upload_to_user?.unit?.full_name || 'N/A'
  }));
});

// Separate documents into pending and received with search functionality
const pendingDocuments = computed(() => {
  let docs = transformedDocuments.value.filter(doc => doc.status === 'pending');
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    docs = docs.filter(doc =>
      Object.values(doc).some(val =>
        String(val).toLowerCase().includes(q)
      )
    );
  }
  return docs;
});

const receivedDocuments = computed(() => {
  let docs = transformedDocuments.value.filter(doc => doc.status === 'received' || doc.status === 'complied');
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    docs = docs.filter(doc =>
      Object.values(doc).some(val =>
        String(val).toLowerCase().includes(q)
      )
    );
  }
  return docs;
});

async function fetchDocuments() {
  try {
    const response = await fetch('/documents/incoming?all=1');
    const data = await response.json();
    documents.value = data.documents || [];
    
    // Debug logging
    console.log('Fetched documents:', {
      totalDocuments: documents.value.length,
      documents: documents.value.map(doc => ({
        id: doc.id,
        subject: doc.subject,
        current_recipient_id: doc.current_recipient_id,
        upload_to_user_id: doc.upload_to_user_id,
        upload_to_user: doc.upload_to_user,
        status: doc.status
      }))
    });
    
    // Debug: Check specifically for complied documents
    const compliedDocs = documents.value.filter(doc => doc.status === 'complied');
    console.log('Complied documents found:', compliedDocs.length, compliedDocs);
    
    // If timer modal is open, update the timer document if it exists in the fetched documents
    if (showTimerModal.value && timerDocument.value) {
      const updatedDoc = documents.value.find(doc => doc.id === timerDocument.value.id);
      if (updatedDoc) {
        const wasComplied = timerDocument.value.status === 'complied';
        const wasPausedBefore = isPaused.value;
        timerDocument.value = updatedDoc;
        
        // If document just became complied, automatically pause
        if (!wasComplied && updatedDoc.status === 'complied' && !isPaused.value) {
          pauseTimer();
          return; // Don't continue if just paused
        }
        
        // Only restart timer countdown if not paused (once paused, never resume)
        // But if it wasn't paused before and still isn't paused, restart the timer
        if (!isPaused.value && !wasPausedBefore) {
          startTimerCountdown(updatedDoc);
        }
      }
    }
  } catch (error) {
    console.error('Error fetching documents:', error);
  }
}





function openForwardModal(document) {
  forwardDocument.value = document;
  showForwardModal.value = true;
  forwardSearch.value = '';
  forwardSelected.value = [];
  fetchSameUnitUsers();
}

function openRejectModal(document) {
  rejectDocument.value = document;
  showRejectModal.value = true;
  rejectForm.value.rejection_reason = '';
}

function openResponseModal(document) {
  responseDocument.value = document;
  showResponseModal.value = true;
}


async function fetchSameUnitUsers() {
  try {
    const resp = await fetch(`/api/users?q=${encodeURIComponent(forwardSearch.value)}`);
    const all = await resp.json();
    const unitName = currentUser.value?.unit?.full_name || '';
    const unitParts = unitName.split('/');
    const userDepartment = unitParts[0]; // e.g., "CPMSD"
    const userDivision = unitParts[1]; // e.g., "CPD", "DO"
    const userRole = currentUser.value?.role;

    let filtered = all;

    // Filter based on user role
    if (userRole === 'encoder' && unitName.endsWith('/DO')) {
      // DO can forward to ALL Clerks in their department (any division)
      // Example: CPMSD/DO can forward to CPMSD/ICTSD Clerk, CPMSD/CPD Clerk, etc.
      filtered = all.filter(u => {
        const recipientUnit = u.unit_name || '';
        const recipientDepartment = recipientUnit.split('/')[0];
        // Must be: same department AND role is clerk
        return u.role === 'clerk' && 
               recipientDepartment === userDepartment && 
               u.id !== currentUser.value?.id;
      });
      
      console.log('DO forwarding - filtered clerks:', {
        userDepartment,
        totalUsers: all.length,
        filteredClerkCount: filtered.length,
        clerks: filtered.map(u => ({ name: u.name, unit: u.unit_name, role: u.role }))
      });
      
    } else if (userRole === 'clerk') {
      // Clerk can only forward to users in their specific division (e.g., CPD)
      // excluding other clerks
      filtered = all.filter(u => {
        const recipientUnit = u.unit_name || '';
        const recipientParts = recipientUnit.split('/');
        const recipientDepartment = recipientParts[0];
        const recipientDivision = recipientParts[1] || '';
        // Must be: same department, same division, not a clerk, and not self
        return recipientDepartment === userDepartment && 
               recipientDivision === userDivision && 
               u.role !== 'clerk' && 
               u.id !== currentUser.value?.id;
      });
      
      console.log('Clerk forwarding - filtered users:', {
        userDepartment,
        userDivision,
        totalUsers: all.length,
        filteredUserCount: filtered.length,
        users: filtered.map(u => ({ name: u.name, unit: u.unit_name, role: u.role }))
      });
      
    } else {
      // Default: same department prefix
      filtered = all.filter(u => {
        const recipientUnit = u.unit_name || '';
        return recipientUnit.startsWith(userDepartment) && u.id !== currentUser.value?.id;
      });
    }

    forwardCandidates.value = filtered;
    
    console.log('Final forward candidates:', forwardCandidates.value.length);
  } catch (e) {
    console.error('Error fetching users for forwarding', e);
  }
}

async function sendForward() {
  if (forwardSelected.value.length === 0) return;
  forwardSending.value = true;
  try {
    const csrfToken = getCSRFToken();
    const res = await fetch(`/documents/${forwardDocument.value.id}/forward`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        recipients: forwardSelected.value.map(u => u.name),
        forward_notes: ''
      })
    });
    if (res.ok) {
      const data = await res.json();
      showNotificationMessage(data.message || 'Forwarded successfully', 'success');
      showForwardModal.value = false;
      await fetchDocuments();
      window.dispatchEvent(new CustomEvent('refreshOutgoingDocuments'));
    } else {
      const err = await res.json().catch(() => ({}));
      showNotificationMessage(err.message || 'Error forwarding document', 'error');
    }
  } catch (e) {
    console.error('Forward error', e);
    showNotificationMessage('Error forwarding document', 'error');
  } finally {
    forwardSending.value = false;
  }
}

async function submitRejection() {
  if (!rejectForm.value.rejection_reason.trim()) return;
  
  rejectSending.value = true;
  try {
    const csrfToken = getCSRFToken();
    
    if (!csrfToken) {
      showNotificationMessage('CSRF token not found. Please refresh the page and try again.', 'error');
      return;
    }
    
    const response = await fetch(`/documents/${rejectDocument.value.id}/reject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        rejection_reason: rejectForm.value.rejection_reason
      })
    });
    
    if (response.ok) {
      const data = await response.json();
      showNotificationMessage(data.message || 'Document rejected successfully', 'success');
      showRejectModal.value = false;
      rejectForm.value.rejection_reason = '';
      await fetchDocuments();
    } else {
      const errorData = await response.json().catch(() => ({}));
      showNotificationMessage(errorData.message || 'Error rejecting document', 'error');
    }
  } catch (error) {
    console.error('Error rejecting document:', error);
    showNotificationMessage('Error rejecting document', 'error');
  } finally {
    rejectSending.value = false;
  }
}







function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString();
}



const showPdfModal = ref(false);
const pdfDocument = ref(null);
const pdfUrl = ref("");

// Check if user can perform actions on a document
function canPerformActions(document) {
  if (!currentUser.value || !document) return false;
  
  // User must be the current recipient
  if (document.current_recipient_id !== currentUser.value.id) return false;
  
  // For comply action, allow all users
  // For other actions, check if user is from a DO unit
  const userUnit = currentUser.value.unit?.full_name || '';
  const isDO = userUnit.endsWith('/DO');
  
  // Debug logging
  console.log('canPerformActions check:', {
    documentId: document.id,
    currentUserId: currentUser.value.id,
    documentCurrentRecipientId: document.current_recipient_id,
    userUnit: userUnit,
    isCurrentRecipient: document.current_recipient_id === currentUser.value.id,
    isDO: isDO
  });
  
  // Allow all current recipients to perform actions (especially comply)
  // This matches the updated backend logic
  return true;
}

function closePdfModal() {
  showPdfModal.value = false;
  pdfUrl.value = "";
  pdfDocument.value = null;
}

async function complyDocument(documentId) {
  if (!confirm('Are you sure you want to mark this document as complied?')) {
    return;
  }
  
  // If timer modal is open for this document, automatically pause it immediately
  if (showTimerModal.value && timerDocument.value && timerDocument.value.id === documentId && !isPaused.value) {
    pauseTimer();
  }
  
  try {
    const csrfToken = getCSRFToken();
    console.log('CSRF Token for comply:', csrfToken);
    
    if (!csrfToken) {
      showNotificationMessage('CSRF token not found. Please refresh the page and try again.', 'error');
      return;
    }
    
    const response = await fetch(`/documents/${documentId}/comply`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        compliance_notes: 'Document marked as complied by user action'
      })
    });
    
    if (response.ok) {
      const data = await response.json();
      console.log('Comply response:', data);
      showNotificationMessage(data.message || 'Document marked as complied', 'success');
      
      // Refresh the documents list to get updated data from server
      console.log('Refreshing documents after comply...');
      await fetchDocuments();
      
      // Update timer document if modal is open for this document
      if (showTimerModal.value && timerDocument.value && timerDocument.value.id === documentId) {
        // Find the updated document
        const updatedDoc = documents.value.find(doc => doc.id === documentId);
        if (updatedDoc) {
          // Save current timer values before updating document (to preserve them)
          const currentTimerCountdown = { ...timerCountdown.value };
          const currentHandlingDuration = { ...handlingDuration.value };
          const currentOverdueTimePassed = { ...overdueTimePassed.value };
          
          timerDocument.value = updatedDoc;
          
          // Ensure timer is paused (it should already be paused from the initial click, but ensure it stays paused)
          if (updatedDoc.status === 'complied' && !isPaused.value) {
            pauseTimer();
          }
          
          // If paused, restore the saved timer values (don't let them get reset)
          if (isPaused.value) {
            timerCountdown.value = currentTimerCountdown;
            handlingDuration.value = currentHandlingDuration;
            overdueTimePassed.value = currentOverdueTimePassed;
            // Save the preserved values to localStorage
            if (updatedDoc.id) {
              const timerKey = `timer_values_${updatedDoc.id}`
              const timerData = {
                timerCountdown: currentTimerCountdown,
                handlingDuration: currentHandlingDuration,
                overdueTimePassed: currentOverdueTimePassed,
                timestamp: new Date().toISOString()
              }
              try {
                localStorage.setItem(timerKey, JSON.stringify(timerData))
              } catch (e) {
                console.error('Error saving preserved timer values:', e)
              }
            }
          } else {
            // Only restart timer countdown if not paused (once paused, never resume)
            startTimerCountdown(updatedDoc);
          }
        }
      }
    } else {
      const errorData = await response.json().catch(() => ({}));
      showNotificationMessage(errorData.message || 'Error marking document as complied', 'error');
    }
  } catch (error) {
    console.error('Error complying with document:', error);
    showNotificationMessage('Error marking document as complied. Please try again.', 'error');
  }
}

async function archiveDocument(documentId) {
  if (!confirm('Are you sure you want to archive this document? This will move it to the archived section.')) {
    return;
  }
  
  try {
    const csrfToken = getCSRFToken();
    console.log('CSRF Token for archive:', csrfToken);
    
    if (!csrfToken) {
      showNotificationMessage('CSRF token not found. Please refresh the page and try again.', 'error');
      return;
    }
    
    const response = await fetch(`/documents/${documentId}/archive`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        archive_notes: 'Document archived by user action'
      })
    });
    
    if (response.ok) {
      const data = await response.json();
      showNotificationMessage(data.message || 'Document archived successfully', 'success');
      await fetchDocuments(); // Refresh the documents list
      
      // Also refresh archived documents if we're on that tab
      if (window.location.hash === '#archived' || document.querySelector('[data-archived-documents]')) {
        // Trigger a custom event to refresh archived documents
        window.dispatchEvent(new CustomEvent('refreshArchivedDocuments'));
      }
    } else {
      const errorData = await response.json().catch(() => ({}));
      showNotificationMessage(errorData.message || 'Error archiving document', 'error');
    }
  } catch (error) {
    console.error('Error archiving document:', error);
    showNotificationMessage('Error archiving document. Please try again.', 'error');
  }
}

// ARTA Color Palette for Priorities - Circle Indicator
// ARTA Color Palette for Priorities - Circle Indicator
// Instant (3 seconds) → Gray
// Urgent (within the day) → Green (higher urgency)
// Standard (1 day) → Green
// Simple (3 days) → Blue
// Complex (7 days) → Red
// Highly Technical (20 days) → Yellow
function getPriorityCircleColor(priority) {
  if (!priority) return 'bg-gray-400'
  
  const priorityLower = priority.toLowerCase()
  if (priorityLower.includes('instant') || priorityLower.includes('3 seconds')) {
    return 'bg-gray-500'
  } else if (priorityLower.includes('urgent') || priorityLower.includes('within the day')) {
    return 'bg-emerald-600'
  } else if (priorityLower.includes('standard') || priorityLower.includes('1 day') || priorityLower.includes('regular')) {
    return 'bg-green-500'
  } else if (priorityLower.includes('simple') || priorityLower.includes('3 days')) {
    return 'bg-blue-500'
  } else if (priorityLower.includes('complex') || priorityLower.includes('7 days')) {
    return 'bg-red-500'
  } else if (priorityLower.includes('highly technical') || priorityLower.includes('20 days')) {
    return 'bg-yellow-500'
  }
  
  return 'bg-gray-400'
}

function getPriorityTextColor(priority) {
  if (!priority) return 'text-gray-600'
  
  const priorityLower = priority.toLowerCase()
  if (priorityLower.includes('instant') || priorityLower.includes('3 seconds')) {
    return 'text-gray-600 font-semibold'
  } else if (priorityLower.includes('urgent') || priorityLower.includes('within the day')) {
    return 'text-emerald-700 font-semibold'
  } else if (priorityLower.includes('standard') || priorityLower.includes('1 day') || priorityLower.includes('regular')) {
    return 'text-green-600 font-semibold'
  } else if (priorityLower.includes('simple') || priorityLower.includes('3 days')) {
    return 'text-blue-600 font-semibold'
  } else if (priorityLower.includes('complex') || priorityLower.includes('7 days')) {
    return 'text-red-600 font-semibold'
  } else if (priorityLower.includes('highly technical') || priorityLower.includes('20 days')) {
    return 'text-yellow-600 font-semibold'
  }
  
  return 'text-gray-600'
}

function getStatusTextColor(status) {
  if (!status) return 'text-gray-600'
  
  const statusLower = status.toLowerCase()
  
  if (statusLower === 'pending') {
    return 'text-yellow-600'
  } else if (statusLower === 'received') {
    return 'text-green-600'
  } else if (statusLower === 'rejected') {
    return 'text-red-600'
  } else if (statusLower === 'forwarded') {
    return 'text-blue-600'
  } else if (statusLower === 'complied') {
    return 'text-purple-600'
  } else if (statusLower === 'archived') {
    return 'text-gray-600'
  }
  
  return 'text-gray-600'
}

// Timer functions
function getPriorityDays(priority) {
  if (!priority) return 0
  
  const priorityLower = priority.toLowerCase()
  if (priorityLower.includes('instant') || priorityLower.includes('3 seconds')) {
    return 3 / 86400 // 3 seconds in days
  } else if (priorityLower.includes('urgent') || priorityLower.includes('within the day')) {
    return 1
  } else if (priorityLower.includes('standard') || priorityLower.includes('1 day') || priorityLower.includes('regular')) {
    return 1
  } else if (priorityLower.includes('simple') || priorityLower.includes('3 days')) {
    return 3
  } else if (priorityLower.includes('complex') || priorityLower.includes('7 days')) {
    return 7
  } else if (priorityLower.includes('highly technical') || priorityLower.includes('20 days')) {
    return 20
  }

  return 0
}

function getReceivedDate(document) {
  // Only use accepted_by_do_at - this is the actual timestamp when document was accepted/received
  // Don't use updated_at as it may change for other reasons
  if (document.accepted_by_do_at) {
    // Parse the date string - handle both ISO format and other formats
    const date = new Date(document.accepted_by_do_at)
    // Check if date is valid
    if (isNaN(date.getTime())) {
      console.error('Invalid accepted_by_do_at date:', document.accepted_by_do_at)
      return null
    }
    return date
  }
  // If no accepted_by_do_at, document hasn't been accepted yet - return null
  return null
}

function calculateTimerStatus(document) {
  if (!document || (document.status !== 'received' && document.status !== 'complied')) {
    return { color: 'bg-gray-300', daysRemaining: 0, daysElapsed: 0, isOverdue: false, receivedDate: null, priorityDays: 0, isInstant: false, isComplied: false }
  }
  
  const priorityDays = getPriorityDays(document.priority)
  const receivedDate = getReceivedDate(document)
  const isInstant = document.priority && document.priority.toLowerCase().includes('instant')
  const isComplied = document.status === 'complied'
  
  if (!receivedDate || priorityDays === 0) {
    return { color: 'bg-gray-300', daysRemaining: 0, daysElapsed: 0, isOverdue: false, receivedDate: null, priorityDays: 0, isInstant: false, isComplied: isComplied }
  }
  
  const now = currentTime.value
  
  // For Instant priority (3 seconds), use milliseconds precision
  if (isInstant) {
    const diffTime = now - receivedDate // milliseconds
    const diffSeconds = diffTime / 1000
    const secondsRemaining = 3 - diffSeconds
    const isOverdue = diffSeconds >= 3
    
    return {
      color: isOverdue ? 'bg-red-500' : 'bg-gray-500',
      daysRemaining: isOverdue ? 0 : secondsRemaining,
      daysElapsed: diffSeconds,
      isOverdue: isOverdue,
      receivedDate: receivedDate,
      priorityDays: 3, // 3 seconds
      isInstant: true,
      isComplied: isComplied
    }
  }
  
  // For other priorities, calculate exact deadline and remaining time
  // Calculate deadline by adding exact milliseconds (priorityDays * 24 hours * 60 minutes * 60 seconds * 1000 ms)
  const deadline = new Date(receivedDate.getTime() + (priorityDays * 24 * 60 * 60 * 1000))
  
  // Calculate remaining time from exact deadline
  const diffTime = deadline - now
  const isOverdue = diffTime <= 0
  
  // Calculate remaining days (can be fractional)
  const totalSecondsRemaining = Math.max(0, diffTime / 1000)
  const daysRemaining = totalSecondsRemaining / (24 * 60 * 60)
  
  // Calculate elapsed days for display
  const elapsedTime = now - receivedDate
  const diffDays = Math.floor(elapsedTime / (1000 * 60 * 60 * 24))
  
  return {
    color: isOverdue ? 'bg-yellow-500' : 'bg-blue-500',
    daysRemaining: isOverdue ? 0 : daysRemaining,
    daysElapsed: diffDays,
    isOverdue: isOverdue,
    receivedDate: receivedDate,
    priorityDays: priorityDays,
    isInstant: false,
    isComplied: isComplied
  }
}

function getDurationPercentage(document) {
  const timerStatus = calculateTimerStatus(document)
  if (!timerStatus.priorityDays || timerStatus.priorityDays === 0) {
    return 0
  }
  if (timerStatus.isOverdue) {
    return 100 // Show 100% filled circle when overdue
  }
  // Calculate percentage: (daysRemaining / priorityDays) * 100
  return Math.max(0, Math.min(100, (timerStatus.daysRemaining / timerStatus.priorityDays) * 100))
}

function getDurationDisplayText(document) {
  const timerStatus = calculateTimerStatus(document)
  if (timerStatus.isOverdue) {
    return 'Due'
  }
  // For instant priorities (3 seconds), always show 0 days
  if (timerStatus.isInstant) {
    return 0
  }
  // For day-based priorities, round down to show whole days remaining
  return Math.max(0, Math.floor(timerStatus.daysRemaining))
}

function getDurationTooltip(document) {
  const timerStatus = calculateTimerStatus(document)
  if (timerStatus.isOverdue) {
    return 'Overdue - Time expired'
  }
  if (timerStatus.isInstant) {
    const seconds = Math.max(0, Math.floor(timerStatus.daysRemaining))
    return `${seconds} ${seconds === 1 ? 'second' : 'seconds'} remaining`
  }
  return `${timerStatus.daysRemaining} ${timerStatus.daysRemaining === 1 ? 'day' : 'days'} remaining`
}

// Helper functions to manage paused documents in localStorage
function getPausedDocuments() {
  try {
    const paused = localStorage.getItem('pausedDocuments')
    return paused ? JSON.parse(paused) : []
  } catch (e) {
    return []
  }
}

function setPausedDocument(documentId) {
  try {
    const paused = getPausedDocuments()
    if (!paused.includes(documentId)) {
      paused.push(documentId)
      localStorage.setItem('pausedDocuments', JSON.stringify(paused))
    }
    // Save timer values for this document
    const timerKey = `timer_values_${documentId}`
    const timerData = {
      timerCountdown: { ...timerCountdown.value },
      handlingDuration: { ...handlingDuration.value },
      overdueTimePassed: { ...overdueTimePassed.value },
      timestamp: new Date().toISOString()
    }
    localStorage.setItem(timerKey, JSON.stringify(timerData))
  } catch (e) {
    console.error('Error saving paused document:', e)
  }
}

function isDocumentPaused(documentId) {
  const paused = getPausedDocuments()
  return paused.includes(documentId)
}

function getPausedTimerValues(documentId) {
  try {
    const timerKey = `timer_values_${documentId}`
    const data = localStorage.getItem(timerKey)
    return data ? JSON.parse(data) : null
  } catch (e) {
    console.error('Error getting paused timer values:', e)
    return null
  }
}

function openTimerModal(document) {
  timerDocument.value = document
  showTimerModal.value = true
  
  // Check if this document was previously paused (from localStorage)
  const wasPaused = isDocumentPaused(document.id)
  
  // Only restore pause state if document is complied OR was explicitly paused
  // For non-complied documents that are not paused, clear any stale pause state
  if (wasPaused && document.status === 'complied') {
    // Document is complied and was paused - restore pause state and timer values
    isPaused.value = true
    pauseStartTime.value = null // Don't track pause start time for persisted pauses
    totalPausedTime.value = 0
    
    // Restore saved timer values
    const savedValues = getPausedTimerValues(document.id)
    if (savedValues) {
      timerCountdown.value = savedValues.timerCountdown || { days: 0, hours: 0, minutes: 0, seconds: 0 }
      handlingDuration.value = savedValues.handlingDuration || { days: 0, hours: 0, minutes: 0, seconds: 0 }
      overdueTimePassed.value = savedValues.overdueTimePassed || { days: 0, hours: 0, minutes: 0, seconds: 0 }
    }
    
    // Don't start the timer - it's permanently paused
    originalDeadline.value = null
    originalReceivedDate.value = null
    return
  } else if (wasPaused && document.status !== 'complied') {
    // Document was paused but is not complied - this shouldn't happen, clear the pause state
    // Remove from paused documents list
    try {
      const paused = getPausedDocuments()
      const index = paused.indexOf(document.id)
      if (index > -1) {
        paused.splice(index, 1)
        localStorage.setItem('pausedDocuments', JSON.stringify(paused))
      }
      // Also remove timer values
      const timerKey = `timer_values_${document.id}`
      localStorage.removeItem(timerKey)
    } catch (e) {
      console.error('Error clearing stale pause state:', e)
    }
    // Reset pause state and start timer normally
    isPaused.value = false
    pauseStartTime.value = null
    totalPausedTime.value = 0
    originalDeadline.value = null
    originalReceivedDate.value = null
  } else {
    // Reset pause state when opening modal for non-paused documents
    isPaused.value = false
    pauseStartTime.value = null
    totalPausedTime.value = 0
    originalDeadline.value = null
    originalReceivedDate.value = null
  }
  
  // Debug: Log document data for complied documents
  if (document.status === 'complied') {
    console.log('Opening timer modal for complied document:', {
      id: document.id,
      status: document.status,
      complied_at: document.complied_at,
      accepted_by_do_at: document.accepted_by_do_at,
      priority: document.priority
    })
  }
  
  // If document is complied, automatically pause and save to localStorage
  if (document.status === 'complied' && !isPaused.value) {
    pauseTimer()
    return // Don't start timer if just paused
  }
  
  // Start the timer normally for non-paused, non-complied documents
  startTimerCountdown(document)
}

function startTimerCountdown(document) {
  // If timer is paused, do nothing - never resume once paused
  if (isPaused.value) {
    return
  }
  
  // Clear any existing interval
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
  
  // Only start timer if document is received or complied
  if (!document || (document.status !== 'received' && document.status !== 'complied')) {
    timerCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  
  const priorityDays = getPriorityDays(document.priority)
  const receivedDate = getReceivedDate(document)
  const isComplied = document.status === 'complied'
  
  if (!receivedDate || priorityDays === 0) {
    timerCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  
  // Calculate deadline based on priority days from the exact received timestamp
  const isInstant = document.priority && document.priority.toLowerCase().includes('instant')
  const deadline = new Date(receivedDate)
  
  if (isInstant) {
    // For Instant priority, add 3 seconds
    deadline.setTime(deadline.getTime() + 3000)
  } else {
    // For other priorities, add exact milliseconds (priorityDays * 24 hours * 60 minutes * 60 seconds * 1000 ms)
    // For example: if received at 5:47 AM on Jan 1 with 3-day priority, deadline is exactly 72 hours later
    deadline.setTime(deadline.getTime() + (priorityDays * 24 * 60 * 60 * 1000))
  }
  
  // Store original values for pause/resume functionality
  originalDeadline.value = new Date(deadline)
  originalReceivedDate.value = new Date(receivedDate)
  
  // Adjust deadline by adding total paused time (if resuming from pause)
  if (totalPausedTime.value > 0) {
    deadline.setTime(deadline.getTime() + totalPausedTime.value)
  }
  
  // Update countdown immediately
  updateCountdown(deadline)
  updateHandlingDuration()
  
  // Update countdown every second
  timerInterval = setInterval(() => {
    if (!isPaused.value) {
      updateCountdown(deadline)
      updateHandlingDuration()
    }
  }, 1000)
}

function updateCountdown(deadline, freezeTime = null) {
  // If paused, don't update the countdown (keep it frozen)
  if (isPaused.value && !freezeTime) {
    return
  }
  
  const now = freezeTime ? new Date(freezeTime) : new Date()
  // Ensure now is a valid date
  if (isNaN(now.getTime())) {
    console.error('Invalid freezeTime in updateCountdown:', freezeTime)
    timerCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  let diff = deadline.getTime() - now.getTime()
  
  if (diff <= 0) {
    // Timer has expired - show 00:00:00:00 for countdown
    timerCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    // Calculate overdue time passed
    const overdueDiff = now - deadline
    const totalSeconds = Math.floor(overdueDiff / 1000)
    const days = Math.floor(totalSeconds / (24 * 60 * 60))
    const hours = Math.floor((totalSeconds % (24 * 60 * 60)) / (60 * 60))
    const minutes = Math.floor((totalSeconds % (60 * 60)) / 60)
    const seconds = totalSeconds % 60
    overdueTimePassed.value = { days, hours, minutes, seconds }
  } else {
    // Calculate days, hours, minutes, seconds from the difference
    const totalSeconds = Math.floor(diff / 1000)
    const days = Math.floor(totalSeconds / (24 * 60 * 60))
    const hours = Math.floor((totalSeconds % (24 * 60 * 60)) / (60 * 60))
    const minutes = Math.floor((totalSeconds % (60 * 60)) / 60)
    const seconds = totalSeconds % 60
    
    timerCountdown.value = { days, hours, minutes, seconds }
    overdueTimePassed.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
  }
  
  // Update handling duration (how long user has been handling the document)
  updateHandlingDuration(freezeTime)
}

function getUserReceivedDate(document) {
  // If document was forwarded to current user, use when they received it
  if (document.current_recipient_id === currentUser.value?.id && document.forwarded_by) {
    // If there's a specific field for when current recipient received it, use that
    // Otherwise, use accepted_by_do_at which should be when they accepted it
    if (document.current_recipient_received_at) {
      const date = new Date(document.current_recipient_received_at)
      if (!isNaN(date.getTime())) {
        return date
      }
    }
    // Fallback to accepted_by_do_at
    if (document.accepted_by_do_at) {
      const date = new Date(document.accepted_by_do_at)
      if (!isNaN(date.getTime())) {
        return date
      }
    }
  }
  // For original recipient or non-forwarded documents, use accepted_by_do_at
  return getReceivedDate(document)
}

function getForwarderFrozenDate(document) {
  // If current user forwarded the document, return when they forwarded it
  if (document.forwarded_by === currentUser.value?.name && document.forwarded_at) {
    const date = new Date(document.forwarded_at)
    if (!isNaN(date.getTime())) {
      return date
    }
  }
  return null
}

function updateHandlingDuration(freezeTime = null) {
  if (!timerDocument.value) return
  
  // Check if current user is the forwarder
  const forwarderFrozenDate = getForwarderFrozenDate(timerDocument.value)
  if (forwarderFrozenDate) {
    // User is the forwarder - freeze duration at forward time (within remaining processing time)
  const receivedDate = getReceivedDate(timerDocument.value)
  if (!receivedDate) {
      handlingDuration.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
      return
    }
    
    // Calculate duration from when they received it to when they forwarded it
    const diff = forwarderFrozenDate - receivedDate
    
    if (diff <= 0) {
      handlingDuration.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
      return
    }
    
    const totalSeconds = Math.floor(diff / 1000)
    const days = Math.floor(totalSeconds / (24 * 60 * 60))
    const hours = Math.floor((totalSeconds % (24 * 60 * 60)) / (60 * 60))
    const minutes = Math.floor((totalSeconds % (60 * 60)) / 60)
    const seconds = totalSeconds % 60
    
    handlingDuration.value = { days, hours, minutes, seconds }
    return
  }
  
  // Current user is the recipient (forwarded to them or original recipient)
  const userReceivedDate = getUserReceivedDate(timerDocument.value)
  if (!userReceivedDate) {
    handlingDuration.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  
  // If paused, use the time when pause started (don't count paused time)
  let now = freezeTime || new Date()
  if (isPaused.value && pauseStartTime.value) {
    // Use the pause start time as the current time (freeze the duration)
    now = new Date(pauseStartTime.value)
  } else if (!isPaused.value && totalPausedTime.value > 0) {
    // Adjust now by subtracting total paused time to get accurate duration
    now = new Date(now.getTime() - totalPausedTime.value)
  }
  
  const diff = now - userReceivedDate
  
  if (diff <= 0) {
    handlingDuration.value = { days: 0, hours: 0, minutes: 0, seconds: 0 }
    return
  }
  
  // Calculate how long the document has been handled by current user
  const totalSeconds = Math.floor(diff / 1000)
  const days = Math.floor(totalSeconds / (24 * 60 * 60))
  const hours = Math.floor((totalSeconds % (24 * 60 * 60)) / (60 * 60))
  const minutes = Math.floor((totalSeconds % (60 * 60)) / 60)
  const seconds = totalSeconds % 60
  
  handlingDuration.value = { days, hours, minutes, seconds }
}

function pauseTimer() {
  if (isPaused.value || !timerDocument.value) {
    return // Already paused
  }
  
  isPaused.value = true
  pauseStartTime.value = new Date()
  
  // Stop the interval
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
  
  // Save paused state and current timer values to localStorage for persistence
  if (timerDocument.value.id) {
    setPausedDocument(timerDocument.value.id)
  }
}

function resumeTimer() {
  // Resume functionality disabled - once paused, timer never resumes
  return
}

function closeTimerModal() {
  showTimerModal.value = false
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
  
  // If paused, save current timer values before closing
  if (isPaused.value && timerDocument.value && timerDocument.value.id) {
    const timerKey = `timer_values_${timerDocument.value.id}`
    const timerData = {
      timerCountdown: { ...timerCountdown.value },
      handlingDuration: { ...handlingDuration.value },
      overdueTimePassed: { ...overdueTimePassed.value },
      timestamp: new Date().toISOString()
    }
    try {
      localStorage.setItem(timerKey, JSON.stringify(timerData))
    } catch (e) {
      console.error('Error saving timer values:', e)
    }
  }
  
  // Don't reset timer values - keep them for next time
  // Don't reset pause state - keep it persisted in localStorage
  // Only reset temporary pause tracking
  pauseStartTime.value = null
  totalPausedTime.value = 0
  originalDeadline.value = null
  originalReceivedDate.value = null
  // Keep isPaused.value as is - it will be restored from localStorage when modal reopens
}

function showNotificationMessage(message, type = 'info') {
  notificationMessage.value = message;
  notificationType.value = type;
  showNotification.value = true;
}

function viewDocument(document) {
  // Show PDF preview modal
  pdfDocument.value = document;
  if (document.file_path) {
    pdfUrl.value = `/storage/${document.file_path}`;
  } else {
    pdfUrl.value = "";
  }
  showPdfModal.value = true;
}


async function acceptDocument(documentId) {
  acceptingId.value = documentId;
  try {
    const csrfToken = getCSRFToken();
    console.log('CSRF Token for accept:', csrfToken);
    
    if (!csrfToken) {
      showNotificationMessage('CSRF token not found. Please refresh the page and try again.', 'error');
      return;
    }
    
    const response = await fetch(`/documents/${documentId}/receive`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    });
    if (response.ok) {
      const data = await response.json();
      // Show success message with automatic forwarding info
      if (data.message) {
        showNotificationMessage(data.message, 'success');
      }
      await fetchDocuments();
    } else {
      const data = await response.json().catch(() => ({}));
      showNotificationMessage(data.message || 'Error accepting document', 'error');
    }
  } catch (error) {
    console.error('Error accepting document:', error);
    showNotificationMessage('Error accepting document', 'error');
  } finally {
    acceptingId.value = null;
  }
}


let pollTimer = null;
let realTimeUpdateInterval = null;

// Reactive timestamp that updates every second for real-time duration updates
const currentTime = ref(new Date());

onMounted(() => {
  fetchDocuments();
  // Poll every 5 seconds for near-instant updates
  pollTimer = setInterval(async () => {
    await fetchDocuments();
  }, 5000);
  
  // Update current time every second for real-time duration calculations
  realTimeUpdateInterval = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
  
  // Listen for custom refresh event from unarchived documents
  window.addEventListener('refreshIncomingDocuments', fetchDocuments);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
  if (realTimeUpdateInterval) clearInterval(realTimeUpdateInterval);
  window.removeEventListener('refreshIncomingDocuments', fetchDocuments);
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
});

// Watch for modal close to clean up timer
watch(showTimerModal, (newValue) => {
  if (!newValue && timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
    timerCountdown.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
    handlingDuration.value = { days: 0, hours: 0, minutes: 0, seconds: 0 };
  }
});

// Watch for document status change to complied - auto pause if timer modal is open
watch(() => timerDocument.value?.status, (newStatus, oldStatus) => {
  if (newStatus === 'complied' && oldStatus !== 'complied' && showTimerModal.value) {
    // Document just became complied - automatically pause
    if (!isPaused.value) {
      pauseTimer();
    }
  }
});
</script> 

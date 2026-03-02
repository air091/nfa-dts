<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75">
    <div class="bg-white rounded-lg shadow-2xl max-w-7xl w-full h-[95vh] relative flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
        <div class="flex items-center space-x-3">
          <h3 class="text-lg font-semibold text-gray-900">Document Viewer</h3>
          <span class="text-sm text-gray-500">{{ document?.subject || 'Document' }}</span>
        </div>
        <button @click="$emit('close')" class="text-gray-600 hover:text-gray-800 transition-colors p-2 rounded-full hover:bg-gray-100">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <!-- Tabs Navigation -->
      <div class="bg-gray-50 border-b border-gray-200">
        <div class="flex">
          <button 
            @click="activeTab = 'details'"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors duration-200',
              activeTab === 'details' 
                ? 'bg-white text-blue-600 border-b-2 border-blue-600' 
                : 'text-gray-600 hover:text-gray-800 hover:bg-gray-100'
            ]"
          >
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
              <span>File Details</span>
            </div>
          </button>
          <button 
            @click="activeTab = 'preview'"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors duration-200',
              activeTab === 'preview' 
                ? 'bg-white text-blue-600 border-b-2 border-blue-600' 
                : 'text-gray-600 hover:text-gray-800 hover:bg-gray-100'
            ]"
          >
            <div class="flex items-center space-x-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
              </svg>
              <span>Page Preview</span>
            </div>
          </button>
        </div>
      </div>

      <!-- Tab Content -->
      <div class="flex-1 overflow-hidden">
        <!-- File Details Tab -->
        <div v-if="activeTab === 'details'" class="h-full overflow-y-auto bg-gray-50 p-6">
          <div class="max-w-6xl mx-auto">
            <h4 class="text-xl font-semibold text-gray-800 mb-6">Document Information</h4>
            
            <!-- First Row - Key Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
              <!-- Tracking Code -->
              <div v-if="document?.tracking_code" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Tracking Code</label>
                <input 
                  type="text" 
                  :value="document.tracking_code" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- Document Type -->
              <div v-if="document?.document_type" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                <input 
                  type="text" 
                  :value="document.document_type" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- Priority -->
              <div v-if="document?.priority" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                <div class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                  <span class="inline-flex items-center gap-2 px-2 py-1 text-xs" :class="getPriorityTextColor(document.priority)">
                    <span class="w-2.5 h-2.5 rounded-full" :class="getPriorityCircleColor(document.priority)"></span>
                    {{ document.priority }}
                  </span>
                </div>
              </div>

              <!-- Status -->
              <div v-if="document?.status" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <div class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="statusColors[document.status]">
                    {{ getStatusText(document.status) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Second Row - Subject (Full Width) -->
            <div v-if="document?.subject" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
              <input 
                type="text" 
                :value="document.subject" 
                readonly
                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
              />
            </div>

            <!-- Third Row - User Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
              <!-- Upload By -->
              <div v-if="document?.upload_by_user?.name" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Uploaded By</label>
                <input 
                  type="text" 
                  :value="document.upload_by_user.name" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- Upload To -->
              <div v-if="document?.upload_to_user?.name" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Sent To</label>
                <input 
                  type="text" 
                  :value="document.upload_to_user.name" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- Originating Office -->
              <div v-if="document?.upload_by_user?.unit?.full_name" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Originating Office</label>
                <input 
                  type="text" 
                  :value="document.upload_by_user.unit.full_name" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>
            </div>

            <!-- Fourth Row - File Information -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <!-- Upload Date -->
              <div v-if="document?.created_at" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">Upload Date</label>
                <input 
                  type="text" 
                  :value="formatDate(document.created_at)" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- File Name -->
              <div v-if="document?.file_path" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">File Name</label>
                <input 
                  type="text" 
                  :value="getFileName(document.file_path)" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>

              <!-- File Size -->
              <div v-if="document?.file_size" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                <label class="block text-sm font-medium text-gray-700 mb-2">File Size</label>
                <input 
                  type="text" 
                  :value="formatFileSize(document.file_size)" 
                  readonly
                  class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium"
                />
              </div>
            </div>

            <!-- Fifth Row - Notes (Full Width) -->
            <div v-if="document?.notes" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
              <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
              <textarea 
                :value="document.notes" 
                readonly
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900 font-medium resize-none"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Page Preview Tab -->
        <div v-if="activeTab === 'preview'" class="h-full relative bg-gray-100 overflow-hidden">
          <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600"></div>
          </div>
          
          <div v-else-if="pdfUrl" class="w-full h-full flex items-center justify-center p-2">
            <div class="relative w-full h-full flex items-center justify-center">
              <div class="bg-white shadow-xl rounded-lg overflow-hidden w-full h-full flex items-center justify-center p-4">
                <canvas ref="pdfCanvas" class="border-0 block max-w-full max-h-full object-contain shadow-lg"></canvas>
              </div>
              
              <!-- Page Navigation -->
              <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-white bg-opacity-95 backdrop-blur-sm rounded-full shadow-2xl border border-gray-200 px-8 py-4">
                <div class="flex items-center space-x-8">
                  <button @click="previousPage" :disabled="currentPage <= 1" class="p-3 rounded-full hover:bg-blue-50 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                  </button>
                  
                  <div class="flex items-center space-x-4 text-sm font-semibold text-gray-800">
                    <span class="text-base">Page</span>
                    <div class="flex items-center space-x-2">
                      <input v-model.number="currentPage" @change="goToPage" type="number" min="1" :max="totalPages" class="w-24 px-4 py-2 text-center border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-bold text-lg" />
                      <span class="text-base">of {{ totalPages || '?' }}</span>
                    </div>
                  </div>
                  
                  <button @click="nextPage" :disabled="currentPage >= totalPages" class="p-3 rounded-full hover:bg-blue-50 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
          
          <div v-else class="flex flex-col items-center space-y-4 text-gray-500">
            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-lg">No PDF available</p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between p-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
        <div class="flex items-center space-x-2 text-sm text-gray-600">
          <span>File: {{ document?.file_path ? getFileName(document.file_path) : 'Unknown' }}</span>
          <span>•</span>
          <span>Pages: {{ totalPages }}</span>
        </div>
        <div class="flex items-center space-x-3">
          <a v-if="pdfUrl" :href="pdfUrl" target="_blank" rel="noopener" class="px-4 py-2 text-blue-600 bg-white border border-blue-600 rounded-md hover:bg-blue-50 transition-colors">Open in browser</a>
          <button @click="downloadPdf" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Download</button>
          <button @click="$emit('close')" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  document: { type: Object, required: true },
  pdfUrl: { type: String, required: true }
})

const emit = defineEmits(['close'])

const activeTab = ref('details')
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const pdfCanvas = ref(null)

let pdfDoc = null
let pdfjsLib = null

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

const priorityColors = {
  'high': 'bg-red-100 text-red-800',
  'medium': 'bg-yellow-100 text-yellow-800',
  'low': 'bg-green-100 text-green-800'
}

const statusColors = {
  'pending': 'bg-yellow-100 text-yellow-800',
  'accepted': 'bg-green-100 text-green-800',
  'rejected': 'bg-red-100 text-red-800',
  'forwarded': 'bg-blue-100 text-blue-800'
}

async function loadPdf() {
  try {
    loading.value = true
    
    if (!pdfjsLib) {
      try {
        pdfjsLib = await import('pdfjs-dist')
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'
      } catch (importError) {
        await loadPdfJsFromCDN()
      }
    }

    const loadingTask = pdfjsLib.getDocument(props.pdfUrl)
    pdfDoc = await loadingTask.promise
    
    totalPages.value = pdfDoc.numPages
    currentPage.value = 1
    
    await renderPage(1)
    loading.value = false
  } catch (error) {
    console.error('Error loading PDF:', error)
    loading.value = false
  }
}

async function loadPdfJsFromCDN() {
  return new Promise((resolve, reject) => {
    if (window.pdfjsLib) {
      pdfjsLib = window.pdfjsLib
      resolve()
      return
    }

    const script = document.createElement('script')
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js'
    script.onload = () => {
      if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'
        pdfjsLib = window.pdfjsLib
        resolve()
      } else {
        reject(new Error('PDF.js failed to load from CDN'))
      }
    }
    script.onerror = () => reject(new Error('Failed to load PDF.js from CDN'))
    document.head.appendChild(script)
  })
}

async function renderPage(pageNum) {
  if (!pdfDoc || !pdfCanvas.value) return
  
  try {
    const page = await pdfDoc.getPage(pageNum)
    const viewport = page.getViewport({ scale: 1.0 })
    
    // Get the modal container dimensions
    const modal = document.querySelector('.max-w-7xl')
    let containerWidth, containerHeight
    
    if (modal) {
      const containerRect = modal.getBoundingClientRect()
      containerWidth = containerRect.width - 100 // Account for padding
      containerHeight = containerRect.height - 300 // Account for header, tabs, footer, navigation
    } else {
      // Fallback to window dimensions
      containerWidth = window.innerWidth * 0.8 - 100
      containerHeight = window.innerHeight * 0.7 - 300
    }
    
    // Calculate scale to fit the PDF in the available space
    const scaleX = containerWidth / viewport.width
    const scaleY = containerHeight / viewport.height
    const scale = Math.min(scaleX, scaleY, 3.0) // Allow up to 3x scale for better readability
    
    const scaledViewport = page.getViewport({ scale })
    
    const canvas = pdfCanvas.value
    const context = canvas.getContext('2d')
    
    // Set canvas dimensions to match the scaled viewport
    canvas.width = scaledViewport.width
    canvas.height = scaledViewport.height
    
    // Set canvas display size for proper rendering
    canvas.style.width = scaledViewport.width + 'px'
    canvas.style.height = scaledViewport.height + 'px'
    
    const renderContext = {
      canvasContext: context,
      viewport: scaledViewport
    }
    
    await page.render(renderContext).promise
  } catch (error) {
    console.error('Error rendering page:', error)
  }
}

function previousPage() {
  if (currentPage.value > 1) {
    currentPage.value--
    renderPage(currentPage.value)
  }
}

function nextPage() {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
    renderPage(currentPage.value)
  }
}

function goToPage() {
  if (currentPage.value < 1) currentPage.value = 1
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  renderPage(currentPage.value)
}

function downloadPdf() {
  if (props.pdfUrl) {
    const link = document.createElement('a')
    link.href = props.pdfUrl
    link.download = props.document?.file_path ? getFileName(props.document.file_path) : 'document.pdf'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }
}

function formatFileSize(bytes) {
  if (!bytes) return 'Unknown'
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i]
}

function getFileName(filePath) {
  if (!filePath) return 'Unknown'
  return filePath.split('/').pop() || filePath
}

function formatDate(dateString) {
  if (!dateString) return 'Unknown'
  return new Date(dateString).toLocaleDateString()
}

function getStatusText(status) {
  const statusMap = {
    'pending': 'Pending',
    'accepted': 'Accepted',
    'rejected': 'Rejected',
    'forwarded': 'Forwarded'
  }
  return statusMap[status] || status
}

watch(() => props.pdfUrl, (newUrl) => {
  if (newUrl) {
    loadPdf()
  }
})

// Watch for tab changes to ensure PDF renders properly
watch(() => activeTab.value, (newTab) => {
  if (newTab === 'preview' && pdfDoc && currentPage.value > 0) {
    // Small delay to ensure DOM is updated
    setTimeout(() => {
      renderPage(currentPage.value)
    }, 100)
  }
})

// Handle window resize to recalculate PDF size
const handleResize = () => {
  if (activeTab.value === 'preview' && currentPage.value > 0) {
    renderPage(currentPage.value)
  }
}

onMounted(() => {
  if (props.pdfUrl) {
    loadPdf()
  }
  
  // Add resize listener
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  // Remove resize listener
  window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>

<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    units: Object,
    filters: Object,
})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const selectedUnit = ref(null)

const createForm = useForm({
    name: '',
    code: '',
    full_name: '',
    description: '',
    is_active: true,
})

const editForm = useForm({
    name: '',
    code: '',
    full_name: '',
    description: '',
    is_active: true,
})

const searchForm = useForm({
    search: props.filters.search || '',
    status: props.filters.status !== undefined ? props.filters.status : '',
})

function normalizeUnitName(value) {
    return (value || '').trim().toUpperCase()
}

function isValidFullNameFormat(value) {
    return /^[A-Za-z0-9]+\/[A-Za-z0-9]+$/.test((value || '').trim())
}

const createFullNameError = computed(() => {
    const fullName = createForm.full_name || ''
    if (!fullName.trim()) {
        return ''
    }
    if (!isValidFullNameFormat(fullName)) {
        return 'Format must be DEPARTMENT/UNIT (example: CPMSD/DO).'
    }

    const normalized = normalizeUnitName(fullName)
    const exists = (props.units?.data || []).some(unit => normalizeUnitName(unit.full_name) === normalized)
    return exists ? `Unit ${normalized} already exists.` : ''
})

const editFullNameError = computed(() => {
    const fullName = editForm.full_name || ''
    if (!fullName.trim()) {
        return ''
    }
    if (!isValidFullNameFormat(fullName)) {
        return 'Format must be DEPARTMENT/UNIT (example: CPMSD/DO).'
    }

    const normalized = normalizeUnitName(fullName)
    const exists = (props.units?.data || []).some(unit =>
        unit.id !== selectedUnit.value?.id && normalizeUnitName(unit.full_name) === normalized
    )
    return exists ? `Unit ${normalized} already exists.` : ''
})

function openCreateModal() {
    showCreateModal.value = true
    createForm.reset()
    createForm.is_active = true
}

function openEditModal(unit) {
    selectedUnit.value = unit
    editForm.name = unit.name
    editForm.code = unit.code
    editForm.full_name = unit.full_name
    editForm.description = unit.description
    editForm.is_active = unit.is_active
    showEditModal.value = true
}

function submitCreate() {
    if (createFullNameError.value) {
        return
    }

    createForm.post(route('admin.units.store'), {
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
        },
    })
}

function submitEdit() {
    if (editFullNameError.value) {
        return
    }

    editForm.put(route('admin.units.update', selectedUnit.value.id), {
        onSuccess: () => {
            showEditModal.value = false
            selectedUnit.value = null
        },
    })
}

function deleteUnit(unit) {
    if (confirm('Are you sure you want to delete this unit?')) {
        router.delete(route('admin.units.destroy', unit.id))
    }
}

function filterUnits() {
    searchForm.get(route('admin.units.index'), {
        preserveState: true,
        replace: true,
    })
}

watch(() => props.filters, (newFilters) => {
    searchForm.search = newFilters.search || ''
    searchForm.status = newFilters.status !== undefined ? newFilters.status : ''
}, { deep: true })
</script>

<template>
    <Head title="Unit Management" />
    
    <AdminLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900">Unit Management</h1>
                <button @click="openCreateModal" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                    Add Unit
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input v-model="searchForm.search" type="text" placeholder="Search units..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               @input="filterUnits">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="searchForm.status" @change="filterUnits"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Units Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="unit in units.data" :key="unit.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ unit.name }}</div>
                                    <div class="text-sm text-gray-500">{{ unit.full_name }}</div>
                                    <div v-if="unit.description" class="text-sm text-gray-500 truncate max-w-xs">{{ unit.description }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ unit.code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ unit.users_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" 
                                          :class="unit.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        {{ unit.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ new Date(unit.created_at).toLocaleDateString() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="openEditModal(unit)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <button @click="deleteUnit(unit)" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="units.links" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link v-if="units.prev_page_url" :href="units.prev_page_url" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </Link>
                            <Link v-if="units.next_page_url" :href="units.next_page_url" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Unit Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showCreateModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Create New Unit</h3>
                    </div>
                    <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="createForm.name" type="text" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input v-model="createForm.code" type="text" required placeholder="e.g., DO, CPD"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input v-model="createForm.full_name" type="text" required placeholder="e.g., CPMSD/DO"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p v-if="createFullNameError" class="mt-1 text-xs text-red-600">{{ createFullNameError }}</p>
                            <p v-if="createForm.errors.full_name" class="mt-1 text-xs text-red-600">{{ createForm.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="createForm.description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <div class="flex items-center">
                            <input v-model="createForm.is_active" type="checkbox" id="create-active"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="create-active" class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                                Cancel
                            </button>
                            <button type="submit" :disabled="createForm.processing || !!createFullNameError" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                                Create Unit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Unit Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showEditModal = false"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Edit Unit</h3>
                    </div>
                    <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input v-model="editForm.name" type="text" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input v-model="editForm.code" type="text" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input v-model="editForm.full_name" type="text" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p v-if="editFullNameError" class="mt-1 text-xs text-red-600">{{ editFullNameError }}</p>
                            <p v-if="editForm.errors.full_name" class="mt-1 text-xs text-red-600">{{ editForm.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="editForm.description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>
                        <div class="flex items-center">
                            <input v-model="editForm.is_active" type="checkbox" id="edit-active"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="edit-active" class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md">
                                Cancel
                            </button>
                            <button type="submit" :disabled="editForm.processing || !!editFullNameError" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                                Update Unit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template> 

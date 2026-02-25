<template>
  <div class="space-y-6">
    <!-- Pending Users Table -->
    <div class="bg-white rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Pending User Approvals</h3>
        <p class="text-sm text-gray-600 mt-1">Users waiting for admin approval and role assignment</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assign Role</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assign Unit</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="user in pendingUsers" :key="user.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.email }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ user.username }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ new Date(user.created_at).toLocaleDateString() }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <select
                  v-model="user.assignedRole"
                  class="text-sm border border-gray-300 rounded px-2 py-1 w-32"
                >
                  <option value="">Select Role</option>
                  <option value="viewer">Viewer</option>
                  <option value="encoder">Encoder</option>
                  <option value="clerk">Clerk</option>
                </select>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div>
                  <select
                    v-model="user.assignedUnit"
                    class="text-sm border border-gray-300 rounded px-2 py-1 w-48"
                    :class="{ 'border-red-500': !!getUnitAssignmentError(user) }"
                  >
                    <option value="">Select Unit</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">
                      {{ unit.full_name }}
                    </option>
                  </select>
                  <p v-if="getUnitAssignmentError(user)" class="mt-1 text-xs text-red-600">
                    {{ getUnitAssignmentError(user) }}
                  </p>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  @click="approveUser(user)"
                  :disabled="!user.assignedRole || !user.assignedUnit"
                  class="text-green-600 hover:text-green-900 disabled:text-gray-400 disabled:cursor-not-allowed"
                >
                  Approve
                </button>
                <button
                  @click="rejectUser(user)"
                  class="text-red-600 hover:text-red-900"
                >
                  Reject
                </button>
              </td>
            </tr>
            <tr v-if="pendingUsers.length === 0">
              <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                No pending users found
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div v-if="pendingUsers.length > 0" class="bg-white rounded-lg shadow p-6">
      <h4 class="text-md font-medium text-gray-900 mb-4">Bulk Actions</h4>
      <div class="flex space-x-4">
        <select
          v-model="bulkRole"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-40"
        >
          <option value="">Select Role for All</option>
          <option value="viewer">Viewer</option>
          <option value="encoder">Encoder</option>
          <option value="clerk">Clerk</option>
        </select>
        <select
          v-model="bulkUnit"
          class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 w-48"
        >
          <option value="">Select Unit for All</option>
          <option v-for="unit in units" :key="unit.id" :value="unit.id">
            {{ unit.full_name }}
          </option>
        </select>
        <button
          @click="bulkApprove"
          :disabled="!bulkRole || !bulkUnit"
          class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          Approve All with Selected Role & Unit
        </button>
        <button
          @click="bulkReject"
          class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
        >
          Reject All
        </button>
      </div>
    </div>
  </div>

  <div v-if="showWarningModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black bg-opacity-50" @click="closeWarningModal"></div>
    <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
      <h4 class="text-lg font-semibold text-red-500 mb-2">Warning</h4>
      <p class="text-sm text-gray-700">{{ warningMessage }}</p>
      <div class="mt-5 flex justify-end">
        <button
          @click="closeWarningModal"
          class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
        >
          OK
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  pendingUsers: {
    type: Array,
    default: () => [],
  },
  units: {
    type: Array,
    default: () => [],
  },
  takenDoUnits: {
    type: Object,
    default: () => ({}),
  },
})

const pendingUsers = ref(props.pendingUsers.map(user => ({
  ...user,
  assignedRole: '',
  assignedUnit: ''
})))
const bulkRole = ref('')
const bulkUnit = ref('')
const showWarningModal = ref(false)
const warningMessage = ref('')

const getUnitById = (unitId) => {
  if (!unitId) {
    return null
  }

  return props.units.find(unit => String(unit.id) === String(unitId)) || null
}

const isDoUnit = (unit) => {
  const fullName = (unit?.full_name || '').trim().toUpperCase()
  return fullName.endsWith('/DO')
}

const getPendingConflict = (user) => {
  const unit = getUnitById(user.assignedUnit)
  if (!unit || !isDoUnit(unit)) {
    return null
  }

  return pendingUsers.value.find(other =>
    other.id !== user.id &&
    String(other.assignedUnit) === String(user.assignedUnit)
  ) || null
}

const getUnitAssignmentError = (user) => {
  if (!user.assignedUnit) {
    return ''
  }

  const selectedUnit = getUnitById(user.assignedUnit)
  if (!selectedUnit) {
    return 'Selected unit is invalid.'
  }

  if (!isDoUnit(selectedUnit)) {
    return ''
  }

  const takenInfo = props.takenDoUnits[String(selectedUnit.id)]
  if (takenInfo) {
    return `There is an existing "${selectedUnit.full_name}" unit account.`
  }

  const pendingConflict = getPendingConflict(user)
  if (pendingConflict) {
    return `${selectedUnit.full_name} is already selected for ${pendingConflict.name}.`
  }

  return ''
}

const canApproveUser = (user) => {
  return Boolean(user.assignedRole && user.assignedUnit && !getUnitAssignmentError(user))
}

const openWarningModal = (message) => {
  warningMessage.value = message
  showWarningModal.value = true
}

const closeWarningModal = () => {
  showWarningModal.value = false
  warningMessage.value = ''
}

// Watch for changes in props and update the local state
watch(() => props.pendingUsers, (newPendingUsers) => {
  const currentAssignments = new Map(
    pendingUsers.value.map(user => [user.id, {
      assignedRole: user.assignedRole,
      assignedUnit: user.assignedUnit,
    }])
  )

  pendingUsers.value = newPendingUsers.map(user => ({
    ...user,
    assignedRole: currentAssignments.get(user.id)?.assignedRole || '',
    assignedUnit: currentAssignments.get(user.id)?.assignedUnit || '',
  }))
}, { deep: true })

const approveUser = (user) => {
  if (!user.assignedRole || !user.assignedUnit) {
    alert('Please select both role and unit before approving')
    return
  }

  const unitError = getUnitAssignmentError(user)
  if (unitError) {
    openWarningModal(unitError)
    return
  }
  
  router.put(`/admin/users/${user.id}/approve`, {
    role: user.assignedRole,
    unit_id: user.assignedUnit,
  }, {
    preserveState: true,
    onSuccess: () => {
      // Remove user from pending list
      pendingUsers.value = pendingUsers.value.filter(u => u.id !== user.id)
    },
    onError: (errors) => {
      const serverMessage = errors?.unit_id || errors?.users || errors?.message || ''
      const selectedUnitName = getUnitById(user.assignedUnit)?.full_name || 'DO'
      if (serverMessage.includes('already assigned')) {
        openWarningModal(`There is an existing "${selectedUnitName}" unit account.`)
        return
      }

      if (serverMessage) {
        openWarningModal(serverMessage)
      }
    },
  })
}

const rejectUser = (user) => {
  if (confirm(`Are you sure you want to reject ${user.name}?`)) {
    router.delete(`/admin/users/${user.id}`)
  }
}

const bulkApprove = () => {
  if (!bulkRole.value || !bulkUnit.value) {
    alert('Please select both role and unit for bulk approval')
    return
  }
  
  const usersToApprove = pendingUsers.value.filter(user => {
    const resolvedRole = user.assignedRole || bulkRole.value
    const resolvedUnit = user.assignedUnit || bulkUnit.value

    if (!resolvedRole || !resolvedUnit) {
      return false
    }

    return !getUnitAssignmentError({ ...user, assignedUnit: resolvedUnit })
  })
  if (usersToApprove.length === 0) {
    alert('Please assign valid roles and units to users before bulk approval')
    return
  }
  
  if (confirm(`Approve ${usersToApprove.length} users with role: ${bulkRole.value} and unit: ${bulkUnit.value}?`)) {
    router.post('/admin/users/bulk-approve', {
      users: usersToApprove.map(user => ({
        id: user.id,
        role: user.assignedRole || bulkRole.value,
        unit_id: user.assignedUnit || bulkUnit.value,
      })),
    }, {
      onSuccess: () => {
        pendingUsers.value = []
        bulkRole.value = ''
        bulkUnit.value = ''
      },
    })
  }
}

const bulkReject = () => {
  if (confirm(`Reject all ${pendingUsers.value.length} pending users?`)) {
    const userIds = pendingUsers.value.map(user => user.id)
    router.post('/admin/users/bulk-reject', {
      user_ids: userIds,
    }, {
      onSuccess: () => {
        pendingUsers.value = []
      },
    })
  }
}
</script>

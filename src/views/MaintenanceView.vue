<script setup>
import { inject, ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'
import { MAINTENANCE_TYPES, maintenanceTypeLabel } from '../utils/maintenanceTypes.js'
import { formatMoney } from '../utils/currency.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const { detail, reload } = inject('carDetail')

const TYPES = MAINTENANCE_TYPES
const form = ref(null)

function typeLabel(type) {
	return maintenanceTypeLabel(t, type)
}

// Ordenação das colunas -- por omissão por data, mais recente no topo.
const sortKey = ref('entryDate')
const sortDir = ref('desc')

const SORT_ACCESSORS = {
	entryDate: (e) => e.entryDate,
	type: (e) => typeLabel(e.type),
	odometer: (e) => e.odometer,
	description: (e) => e.description,
	cost: (e) => e.cost,
	workshop: (e) => e.workshop,
	nextDueDate: (e) => e.nextDueDate,
}

function toggleSort(key) {
	if (sortKey.value === key) {
		sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
	} else {
		sortKey.value = key
		// Datas e custo fazem mais sentido a começar do maior/mais recente;
		// texto (tipo, descrição, oficina) faz mais sentido A-Z primeiro.
		sortDir.value = (key === 'entryDate' || key === 'nextDueDate' || key === 'odometer' || key === 'cost') ? 'desc' : 'asc'
	}
}

const sortedEntries = computed(() => {
	const accessor = SORT_ACCESSORS[sortKey.value]
	const dir = sortDir.value === 'asc' ? 1 : -1
	return [...detail.maintenanceEntries].sort((a, b) => {
		const va = accessor(a)
		const vb = accessor(b)
		// Valores em falta ficam sempre no fim, seja qual for a direção --
		// senão "sem custo definido" salta para o topo ao ordenar por custo
		// descendente, o que confunde mais do que ajuda.
		if (va === null || va === undefined || va === '') return vb === null || vb === undefined || vb === '' ? 0 : 1
		if (vb === null || vb === undefined || vb === '') return -1
		if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir
		return String(va).localeCompare(String(vb)) * dir
	})
})

function errorMessage(e) {
	return e?.response?.data?.message || e?.message || String(e)
}

function today() {
	return new Date().toISOString().slice(0, 10)
}

function openNewForm() {
	form.value = {
		id: null,
		entryDate: today(),
		type: 'oil_change',
		odometer: '',
		description: '',
		cost: '',
		workshop: '',
		nextDueDate: '',
		nextDueOdometer: '',
		notes: '',
	}
}

function openEditForm(entry) {
	form.value = {
		id: entry.id,
		entryDate: entry.entryDate,
		type: entry.type,
		odometer: entry.odometer ?? '',
		description: entry.description ?? '',
		cost: entry.cost ?? '',
		workshop: entry.workshop ?? '',
		nextDueDate: entry.nextDueDate ?? '',
		nextDueOdometer: entry.nextDueOdometer ?? '',
		notes: entry.notes ?? '',
	}
}

function cancelForm() {
	form.value = null
}

function onDialogOpenChange(isOpen) {
	if (!isOpen) {
		form.value = null
	}
}

async function submitForm() {
	if (!form.value.entryDate || !form.value.type) {
		window.alert(t('carfuelmaintance', 'Please fill in the date and type.'))
		return
	}
	const payload = {
		entryDate: form.value.entryDate,
		type: form.value.type,
		odometer: form.value.odometer === '' ? null : Number(form.value.odometer),
		odometerProvided: true,
		description: form.value.description || null,
		descriptionProvided: true,
		cost: form.value.cost === '' ? null : Number(form.value.cost),
		costProvided: true,
		workshop: form.value.workshop || null,
		workshopProvided: true,
		nextDueDate: form.value.nextDueDate || null,
		nextDueDateProvided: true,
		nextDueOdometer: form.value.nextDueOdometer === '' ? null : Number(form.value.nextDueOdometer),
		nextDueOdometerProvided: true,
		notes: form.value.notes || null,
		notesProvided: true,
	}
	try {
		if (form.value.id === null) {
			await api.createMaintenance(props.id, payload)
		} else {
			await api.updateMaintenance(form.value.id, payload)
		}
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not save the maintenance entry: {message}', { message: errorMessage(e) }))
		return
	}
	form.value = null
	await reload()
}

async function removeEntry(id) {
	if (!window.confirm(t('carfuelmaintance', 'Delete this maintenance entry?'))) {
		return
	}
	try {
		await api.deleteMaintenance(id)
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not delete the maintenance entry: {message}', { message: errorMessage(e) }))
		return
	}
	await reload()
}

const dialogButtons = computed(() => [
	{ label: t('carfuelmaintance', 'Cancel'), callback: cancelForm },
	{ label: t('carfuelmaintance', 'Save'), type: 'primary', nativeType: 'submit' },
])
</script>

<template>
	<div class="maintenance-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewForm">{{ t('carfuelmaintance', '+ Maintenance entry') }}</button>
		</div>
		<table v-if="detail.maintenanceEntries.length" class="entries-table">
			<thead>
				<tr>
					<th v-for="col in [
						['entryDate', 'Date'],
						['type', 'Type'],
						['odometer', 'Odometer'],
						['description', 'Description'],
						['cost', 'Cost'],
						['workshop', 'Workshop'],
						['nextDueDate', 'Next due'],
					]" :key="col[0]">
						<button type="button" class="sort-btn" @click="toggleSort(col[0])">
							{{ t('carfuelmaintance', col[1]) }}
							<span v-if="sortKey === col[0]" class="sort-arrow">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
						</button>
					</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="entry in sortedEntries" :key="entry.id">
					<td>{{ entry.entryDate }}</td>
					<td>{{ typeLabel(entry.type) }}</td>
					<td>{{ entry.odometer !== null ? `${entry.odometer} ${detail.stats.odometerUnit}` : '—' }}</td>
					<td>{{ entry.description || '—' }}</td>
					<td>{{ formatMoney(entry.cost, detail.stats.currencySymbol) ?? '—' }}</td>
					<td>{{ entry.workshop || '—' }}</td>
					<td>
						<template v-if="entry.nextDueDate || entry.nextDueOdometer !== null">
							{{ entry.nextDueDate || '—' }}
							<template v-if="entry.nextDueOdometer !== null"> / {{ entry.nextDueOdometer }} {{ detail.stats.odometerUnit }}</template>
						</template>
						<template v-else>—</template>
					</td>
					<td class="row-actions">
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Edit')" :title="t('carfuelmaintance', 'Edit')" @click="openEditForm(entry)">
							<Pencil :size="16" />
						</button>
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Delete')" :title="t('carfuelmaintance', 'Delete')" @click="removeEntry(entry.id)">
							<Delete :size="16" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="empty">{{ t('carfuelmaintance', 'No maintenance entries yet.') }}</p>

		<NcDialog
			:open="form !== null"
			:name="form?.id === null ? t('carfuelmaintance', '+ Maintenance entry') : t('carfuelmaintance', 'Edit maintenance entry')"
			is-form
			size="normal"
			:buttons="dialogButtons"
			@update:open="onDialogOpenChange"
			@submit.prevent="submitForm">
			<div v-if="form" class="dialog-form">
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Date') }}</span>
						<input v-model="form.entryDate" type="date" autofocus required>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Type') }}</span>
						<select v-model="form.type">
							<option v-for="ty in TYPES" :key="ty" :value="ty">{{ typeLabel(ty) }}</option>
						</select>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Odometer') }}</span>
						<input v-model="form.odometer" type="number" step="0.1" min="0">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Cost') }} ({{ detail.stats.currencySymbol }})</span>
						<input v-model="form.cost" type="number" step="0.01" min="0">
					</label>
				</div>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Description') }}</span>
					<textarea v-model="form.description" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Workshop') }}</span>
					<input v-model="form.workshop" type="text">
				</label>
				<p class="hint">{{ t('carfuelmaintance', 'Optional — set a next due date and/or odometer to get a reminder on the Overview tab.') }}</p>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Next due date') }}</span>
						<input v-model="form.nextDueDate" type="date">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Next due odometer') }}</span>
						<input v-model="form.nextDueOdometer" type="number" step="0.1" min="0">
					</label>
				</div>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Notes') }}</span>
					<textarea v-model="form.notes" rows="2"></textarea>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.maintenance-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	margin-bottom: 16px;
}

.entries-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.entries-table th,
.entries-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.entries-table th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	padding: 0;
}

.sort-btn {
	display: flex;
	align-items: center;
	gap: 4px;
	width: 100%;
	height: 100%;
	background: none;
	border: none;
	color: inherit;
	font: inherit;
	font-weight: inherit;
	text-align: left;
	cursor: pointer;
	padding: 6px 8px;
}

.sort-btn:hover {
	background-color: rgba(0, 0, 0, 0.1);
}

.sort-arrow {
	font-size: 10px;
}

.empty {
	color: var(--color-text-maxcontrast);
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 12px;
}

.row-actions {
	white-space: nowrap;
}

.icon-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	vertical-align: middle;
}

.icon-btn:hover {
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
}

.dialog-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 4px 0 16px;
}

.dialog-row {
	display: flex;
	gap: 16px;
}

.dialog-row .dialog-field {
	flex: 1;
}

.dialog-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 13px;
}

.dialog-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.dialog-field input,
.dialog-field select,
.dialog-field textarea {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus,
.dialog-field select:focus,
.dialog-field textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.hint {
	margin: -8px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>

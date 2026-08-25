<script setup>
import { inject, ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const { detail, reload } = inject('carDetail')

const UNITS = ['L', 'gal', 'kWh']
const form = ref(null)

function today() {
	return new Date().toISOString().slice(0, 10)
}

function openNewForm() {
	form.value = {
		id: null,
		entryDate: today(),
		odometer: '',
		quantity: '',
		unit: 'L',
		pricePerUnit: '',
		totalCost: '',
		fullTank: true,
		station: '',
		notes: '',
	}
}

function openEditForm(entry) {
	form.value = {
		id: entry.id,
		entryDate: entry.entryDate,
		odometer: entry.odometer,
		quantity: entry.quantity,
		unit: entry.unit,
		pricePerUnit: entry.pricePerUnit ?? '',
		totalCost: entry.totalCost ?? '',
		fullTank: entry.fullTank,
		station: entry.station ?? '',
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
	if (!form.value.entryDate || !form.value.odometer || !form.value.quantity) {
		window.alert(t('carfuelmaintance', 'Please fill in the date, odometer and quantity.'))
		return
	}
	const payload = {
		entryDate: form.value.entryDate,
		odometer: Number(form.value.odometer),
		quantity: Number(form.value.quantity),
		unit: form.value.unit,
		pricePerUnit: form.value.pricePerUnit === '' ? null : Number(form.value.pricePerUnit),
		pricePerUnitProvided: true,
		totalCost: form.value.totalCost === '' ? null : Number(form.value.totalCost),
		totalCostProvided: true,
		fullTank: form.value.fullTank,
		station: form.value.station || null,
		stationProvided: true,
		notes: form.value.notes || null,
		notesProvided: true,
	}
	if (form.value.id === null) {
		await api.createFuel(props.id, payload)
	} else {
		await api.updateFuel(form.value.id, payload)
	}
	form.value = null
	await reload()
}

async function removeEntry(id) {
	if (!window.confirm(t('carfuelmaintance', 'Delete this fuel entry?'))) {
		return
	}
	await api.deleteFuel(id)
	await reload()
}

const dialogButtons = computed(() => [
	{ label: t('carfuelmaintance', 'Cancel'), callback: cancelForm },
	{ label: t('carfuelmaintance', 'Save'), type: 'primary', nativeType: 'submit' },
])
</script>

<template>
	<div class="fuel-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewForm">{{ t('carfuelmaintance', '+ Fuel entry') }}</button>
		</div>
		<table v-if="detail.fuelEntries.length" class="entries-table">
			<thead>
				<tr>
					<th>{{ t('carfuelmaintance', 'Date') }}</th>
					<th>{{ t('carfuelmaintance', 'Odometer') }}</th>
					<th>{{ t('carfuelmaintance', 'Quantity') }}</th>
					<th>{{ t('carfuelmaintance', 'Full tank') }}</th>
					<th>{{ t('carfuelmaintance', 'Price/unit') }}</th>
					<th>{{ t('carfuelmaintance', 'Total cost') }}</th>
					<th>{{ t('carfuelmaintance', 'Station') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="entry in detail.fuelEntries" :key="entry.id">
					<td>{{ entry.entryDate }}</td>
					<td>{{ entry.odometer }} {{ detail.stats.odometerUnit }}</td>
					<td>{{ entry.quantity }} {{ entry.unit }}</td>
					<td>{{ entry.fullTank ? t('carfuelmaintance', 'Yes') : t('carfuelmaintance', 'No') }}</td>
					<td>{{ entry.pricePerUnit ?? '—' }}</td>
					<td>{{ entry.totalCost ?? '—' }}</td>
					<td>{{ entry.station || '—' }}</td>
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
		<p v-else class="empty">{{ t('carfuelmaintance', 'No fuel entries yet.') }}</p>

		<NcDialog
			:open="form !== null"
			:name="form?.id === null ? t('carfuelmaintance', '+ Fuel entry') : t('carfuelmaintance', 'Edit fuel entry')"
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
						<span class="dialog-label">{{ t('carfuelmaintance', 'Odometer') }}</span>
						<input v-model="form.odometer" type="number" step="0.1" min="0" required>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Quantity') }}</span>
						<input v-model="form.quantity" type="number" step="0.001" min="0" required>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Unit') }}</span>
						<select v-model="form.unit">
							<option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
						</select>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Price per unit') }}</span>
						<input v-model="form.pricePerUnit" type="number" step="0.0001" min="0">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Total cost') }}</span>
						<input v-model="form.totalCost" type="number" step="0.01" min="0">
					</label>
				</div>
				<p class="hint">{{ t('carfuelmaintance', 'Fill in either the price per unit or the total cost — the other is calculated automatically.') }}</p>
				<label class="dialog-field checkbox-field">
					<input v-model="form.fullTank" type="checkbox">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Full tank (needed for accurate consumption)') }}</span>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Station') }}</span>
					<input v-model="form.station" type="text">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Notes') }}</span>
					<textarea v-model="form.notes" rows="2"></textarea>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.fuel-view {
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

.checkbox-field {
	flex-direction: row;
	align-items: center;
	gap: 8px;
}

.checkbox-field input {
	width: auto;
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

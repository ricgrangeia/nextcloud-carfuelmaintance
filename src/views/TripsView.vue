<script setup>
import { inject, ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'
import { TRIP_PURPOSES, tripPurposeLabel } from '../utils/tripPurposes.js'
import { formatMoney } from '../utils/currency.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const { detail, reload } = inject('carDetail')

const form = ref(null)

function purposeLabel(purpose) {
	return tripPurposeLabel(t, purpose)
}

function errorMessage(e) {
	return e?.response?.data?.message || e?.message || String(e)
}

// The primary (most-logged) fuel type's cost-per-distance at the latest
// known price — used to estimate each trip's fuel cost, since trips aren't
// tied to a specific fill-up.
const costPerDistance = computed(() => {
	const groups = detail.value.stats.consumptionByFuelType
	if (!groups.length) {
		return null
	}
	const primary = groups[0]
	return detail.value.stats.consumptionFormat === 'perUnit'
		? primary.costPerDistanceAtLastPrice
		: (primary.costPer100AtLastPrice !== null ? primary.costPer100AtLastPrice / 100 : null)
})

function estimatedFuelCost(trip) {
	if (costPerDistance.value === null) {
		return null
	}
	return Math.round(trip.distance * costPerDistance.value * 100) / 100
}

function totalTripCost(trip) {
	const fuel = estimatedFuelCost(trip) ?? 0
	const tolls = trip.tolls ?? 0
	const other = trip.otherCosts ?? 0
	return Math.round((fuel + tolls + other) * 100) / 100
}

function today() {
	return new Date().toISOString().slice(0, 10)
}

function openNewForm() {
	form.value = {
		id: null,
		tripDate: today(),
		purpose: 'business',
		origin: '',
		destination: '',
		startOdometer: detail.value.stats.currentOdometer || '',
		endOdometer: '',
		tolls: '',
		otherCosts: '',
		notes: '',
	}
}

function openEditForm(trip) {
	form.value = {
		id: trip.id,
		tripDate: trip.tripDate,
		purpose: trip.purpose,
		origin: trip.origin ?? '',
		destination: trip.destination ?? '',
		startOdometer: trip.startOdometer,
		endOdometer: trip.endOdometer,
		tolls: trip.tolls ?? '',
		otherCosts: trip.otherCosts ?? '',
		notes: trip.notes ?? '',
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
	if (!form.value.tripDate || form.value.startOdometer === '' || form.value.endOdometer === '') {
		window.alert(t('carfuelmaintance', 'Please fill in the date, start and end odometer.'))
		return
	}
	const payload = {
		tripDate: form.value.tripDate,
		purpose: form.value.purpose,
		origin: form.value.origin || null,
		originProvided: true,
		destination: form.value.destination || null,
		destinationProvided: true,
		startOdometer: Number(form.value.startOdometer),
		endOdometer: Number(form.value.endOdometer),
		tolls: form.value.tolls === '' ? null : Number(form.value.tolls),
		tollsProvided: true,
		otherCosts: form.value.otherCosts === '' ? null : Number(form.value.otherCosts),
		otherCostsProvided: true,
		notes: form.value.notes || null,
		notesProvided: true,
	}
	try {
		if (form.value.id === null) {
			await api.createTrip(props.id, payload)
		} else {
			await api.updateTrip(form.value.id, payload)
		}
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not save the trip: {message}', { message: errorMessage(e) }))
		return
	}
	form.value = null
	await reload()
}

async function removeTrip(id) {
	if (!window.confirm(t('carfuelmaintance', 'Delete this trip?'))) {
		return
	}
	try {
		await api.deleteTrip(id)
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not delete the trip: {message}', { message: errorMessage(e) }))
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
	<div class="trips-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewForm">{{ t('carfuelmaintance', '+ Trip') }}</button>
		</div>
		<p v-if="costPerDistance === null" class="hint">
			{{ t('carfuelmaintance', 'Estimated fuel cost needs at least two full-tank fill-ups with a price logged — until then it\'s left out of the total.') }}
		</p>
		<table v-if="detail.trips.length" class="entries-table">
			<thead>
				<tr>
					<th>{{ t('carfuelmaintance', 'Date') }}</th>
					<th>{{ t('carfuelmaintance', 'Purpose') }}</th>
					<th>{{ t('carfuelmaintance', 'Route') }}</th>
					<th>{{ t('carfuelmaintance', 'Distance') }}</th>
					<th>{{ t('carfuelmaintance', 'Est. fuel cost') }}</th>
					<th>{{ t('carfuelmaintance', 'Tolls') }}</th>
					<th>{{ t('carfuelmaintance', 'Other costs') }}</th>
					<th>{{ t('carfuelmaintance', 'Total cost') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="trip in detail.trips" :key="trip.id">
					<td>{{ trip.tripDate }}</td>
					<td>{{ purposeLabel(trip.purpose) }}</td>
					<td>
						<template v-if="trip.origin || trip.destination">{{ trip.origin || '?' }} → {{ trip.destination || '?' }}</template>
						<template v-else>—</template>
					</td>
					<td>{{ trip.distance }} {{ detail.stats.odometerUnit }}</td>
					<td>{{ formatMoney(estimatedFuelCost(trip), detail.stats.currencySymbol) ?? '—' }}</td>
					<td>{{ formatMoney(trip.tolls, detail.stats.currencySymbol) ?? '—' }}</td>
					<td>{{ formatMoney(trip.otherCosts, detail.stats.currencySymbol) ?? '—' }}</td>
					<td>{{ formatMoney(totalTripCost(trip), detail.stats.currencySymbol) }}</td>
					<td class="row-actions">
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Edit')" :title="t('carfuelmaintance', 'Edit')" @click="openEditForm(trip)">
							<Pencil :size="16" />
						</button>
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Delete')" :title="t('carfuelmaintance', 'Delete')" @click="removeTrip(trip.id)">
							<Delete :size="16" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="empty">{{ t('carfuelmaintance', 'No trips yet.') }}</p>

		<NcDialog
			:open="form !== null"
			:name="form?.id === null ? t('carfuelmaintance', '+ Trip') : t('carfuelmaintance', 'Edit trip')"
			is-form
			size="normal"
			:buttons="dialogButtons"
			@update:open="onDialogOpenChange"
			@submit.prevent="submitForm">
			<div v-if="form" class="dialog-form">
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Date') }}</span>
						<input v-model="form.tripDate" type="date" autofocus required>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Purpose') }}</span>
						<select v-model="form.purpose">
							<option v-for="p in TRIP_PURPOSES" :key="p" :value="p">{{ purposeLabel(p) }}</option>
						</select>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Origin') }}</span>
						<input v-model="form.origin" type="text">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Destination') }}</span>
						<input v-model="form.destination" type="text">
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Start odometer') }}</span>
						<input v-model="form.startOdometer" type="number" step="0.1" min="0" required>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'End odometer') }}</span>
						<input v-model="form.endOdometer" type="number" step="0.1" min="0" required>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Tolls') }} ({{ detail.stats.currencySymbol }})</span>
						<input v-model="form.tolls" type="number" step="0.01" min="0">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Other costs') }} ({{ detail.stats.currencySymbol }})</span>
						<input v-model="form.otherCosts" type="number" step="0.01" min="0">
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
.trips-view {
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

.hint {
	margin: 0 0 16px;
	font-size: 12px;
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
</style>

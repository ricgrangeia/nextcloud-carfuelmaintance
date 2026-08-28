<script setup>
import { ref, computed, provide, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Cog from 'vue-material-design-icons/Cog.vue'
import api from '../api/client.js'
import { loadCars } from '../store/index.js'
import { fuelTypeLabel } from '../utils/fuelTypes.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const router = useRouter()

const FUEL_TYPES = ['gasoline', 'diesel', 'lpg', 'electric', 'hybrid', 'other']
const ODOMETER_UNITS = ['km', 'mi']

function typeLabel(type) {
	return fuelTypeLabel(t, type)
}

const detail = ref(null)
const settingsOpen = ref(false)
const settingsForm = ref(null)

async function load() {
	detail.value = await api.getCar(props.id)
}

watch(() => props.id, load, { immediate: true })

provide('carDetail', {
	detail,
	reload: load,
})

function errorMessage(e) {
	return e?.response?.data?.message || e?.message || String(e)
}

function openSettings() {
	settingsForm.value = { ...detail.value.car }
}

function closeSettings(isOpen) {
	settingsOpen.value = isOpen
	if (!isOpen) {
		settingsForm.value = null
	}
}

async function saveSettings() {
	try {
		await api.updateCar(props.id, {
			name: settingsForm.value.name,
			brand: settingsForm.value.brand,
			brandProvided: true,
			model: settingsForm.value.model,
			modelProvided: true,
			plate: settingsForm.value.plate,
			plateProvided: true,
			year: settingsForm.value.year ? Number(settingsForm.value.year) : null,
			yearProvided: true,
			fuelType: settingsForm.value.fuelType,
			secondaryFuelType: settingsForm.value.secondaryFuelType || null,
			secondaryFuelTypeProvided: true,
			initialOdometer: Number(settingsForm.value.initialOdometer) || 0,
			odometerUnit: settingsForm.value.odometerUnit,
			notes: settingsForm.value.notes,
			notesProvided: true,
			purchasePrice: settingsForm.value.purchasePrice === '' ? null : Number(settingsForm.value.purchasePrice),
			purchasePriceProvided: true,
			purchaseDate: settingsForm.value.purchaseDate || null,
			purchaseDateProvided: true,
		})
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not save the car: {message}', { message: errorMessage(e) }))
		return
	}
	settingsForm.value = null
	settingsOpen.value = false
	await Promise.all([load(), loadCars()])
}

async function archiveCar() {
	try {
		await api.updateCar(props.id, { archived: !detail.value.car.archived })
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not update the car: {message}', { message: errorMessage(e) }))
		return
	}
	await Promise.all([load(), loadCars()])
}

async function removeCar() {
	if (!window.confirm(t('carfuelmaintance', 'Delete this car and all its fuel/maintenance entries? This cannot be undone.'))) {
		return
	}
	try {
		await api.deleteCar(props.id)
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not delete the car: {message}', { message: errorMessage(e) }))
		return
	}
	await loadCars()
	router.push({ name: 'cars' })
}

const settingsDialogButtons = computed(() => [
	{ label: t('carfuelmaintance', 'Cancel'), callback: () => closeSettings(false) },
	{ label: t('carfuelmaintance', 'Save'), type: 'primary', nativeType: 'submit' },
])
</script>

<template>
	<div v-if="detail" class="car-view">
		<nav class="car-tabs">
			<router-link :to="{ name: 'overview', params: { id } }">{{ t('carfuelmaintance', 'Overview') }}</router-link>
			<router-link :to="{ name: 'fuel', params: { id } }">{{ t('carfuelmaintance', 'Fuel') }}</router-link>
			<router-link :to="{ name: 'maintenance', params: { id } }">{{ t('carfuelmaintance', 'Maintenance') }}</router-link>
				<router-link :to="{ name: 'trips', params: { id } }">{{ t('carfuelmaintance', 'Trips') }}</router-link>
			<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Car settings')" :title="t('carfuelmaintance', 'Car settings')" @click="openSettings(); settingsOpen = true">
				<Cog :size="18" />
			</button>
		</nav>
		<router-view :id="id" />

		<NcDialog
			:open="settingsOpen"
			:name="t('carfuelmaintance', 'Car settings')"
			is-form
			size="normal"
			:buttons="settingsDialogButtons"
			@update:open="closeSettings"
			@submit.prevent="saveSettings">
			<div v-if="settingsForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Name') }}</span>
					<input v-model="settingsForm.name" type="text" autofocus required>
				</label>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Brand') }}</span>
						<input v-model="settingsForm.brand" type="text">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Model') }}</span>
						<input v-model="settingsForm.model" type="text">
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Plate') }}</span>
						<input v-model="settingsForm.plate" type="text">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Year') }}</span>
						<input v-model="settingsForm.year" type="number">
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Fuel type') }}</span>
						<select v-model="settingsForm.fuelType">
							<option v-for="f in FUEL_TYPES" :key="f" :value="f">{{ typeLabel(f) }}</option>
						</select>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Odometer unit') }}</span>
						<select v-model="settingsForm.odometerUnit">
							<option v-for="u in ODOMETER_UNITS" :key="u" :value="u">{{ u }}</option>
						</select>
					</label>
				</div>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Secondary fuel type (bifuel cars, e.g. gasoline + LPG)') }}</span>
					<select v-model="settingsForm.secondaryFuelType">
						<option :value="null">{{ t('carfuelmaintance', 'None — single fuel') }}</option>
						<option v-for="f in FUEL_TYPES" :key="f" :value="f">{{ typeLabel(f) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Starting odometer reading') }}</span>
					<input v-model="settingsForm.initialOdometer" type="number" step="0.1" min="0">
				</label>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Purchase price') }}</span>
						<input v-model="settingsForm.purchasePrice" type="number" step="0.01" min="0">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Purchase date') }}</span>
						<input v-model="settingsForm.purchaseDate" type="date">
					</label>
				</div>
				<p class="hint">{{ t('carfuelmaintance', 'Optional — used to compute total cost of ownership on the Overview tab.') }}</p>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Notes') }}</span>
					<textarea v-model="settingsForm.notes" rows="2"></textarea>
				</label>
				<div class="dialog-actions-row">
					<button type="button" class="link-btn" @click="archiveCar">
						{{ detail.car.archived ? t('carfuelmaintance', 'Restore car') : t('carfuelmaintance', 'Archive car') }}
					</button>
					<button type="button" class="link-btn danger" @click="removeCar">
						{{ t('carfuelmaintance', 'Delete car') }}
					</button>
				</div>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.car-view {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.car-tabs {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 4px;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.car-tabs a {
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	color: var(--color-text-maxcontrast);
	text-decoration: none;
}

.car-tabs a:hover {
	background-color: var(--color-background-hover);
}

.car-tabs a.router-link-exact-active {
	color: var(--color-main-text);
	background-color: var(--color-primary-element-light);
	font-weight: bold;
}

.icon-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	margin-left: 8px;
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

.dialog-actions-row {
	display: flex;
	justify-content: space-between;
	padding-top: 8px;
	border-top: 1px solid var(--color-border);
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 13px;
	padding: 4px 0;
}

.link-btn.danger {
	color: var(--color-error-text, #a02020);
}
</style>

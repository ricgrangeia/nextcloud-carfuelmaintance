<script setup>
import { ref, computed, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'
import { state as carState, loadCars } from '../store/index.js'
import { PART_CATEGORIES, partCategoryLabel } from '../utils/partCategories.js'
import { formatMoney } from '../utils/currency.js'

const CONDITIONS = ['new', 'used']
const MAX_IMAGE_DIMENSION = 1600
const IMAGE_QUALITY = 0.82

const parts = ref([])
const currencySymbol = ref('€')
const filterCarId = ref('')
const form = ref(null)
const imageFile = ref(null)
const imagePreview = ref(null)

function categoryLabel(category) {
	return category ? partCategoryLabel(t, category) : '—'
}

function conditionLabel(condition) {
	return condition === 'used' ? t('carfuelmaintance', 'Used') : t('carfuelmaintance', 'New')
}

function carName(carId) {
	if (carId === null) {
		return t('carfuelmaintance', 'General stock')
	}
	const car = carState.cars.find((c) => c.id === carId)
	return car ? car.name : t('carfuelmaintance', 'General stock')
}

function errorMessage(e) {
	return e?.response?.data?.message || e?.message || String(e)
}

async function load() {
	parts.value = await api.listParts(filterCarId.value || null)
	const settings = await api.getSettings()
	currencySymbol.value = settings.currencySymbol
}

onMounted(async () => {
	if (carState.cars.length === 0) {
		await loadCars()
	}
	await load()
})

const filteredParts = computed(() => parts.value)

function openNewForm() {
	form.value = {
		id: null,
		name: '',
		carId: filterCarId.value || '',
		reference: '',
		condition: 'new',
		category: '',
		location: '',
		quantity: 1,
		cost: '',
		notes: '',
	}
	imageFile.value = null
	imagePreview.value = null
}

function openEditForm(part) {
	form.value = {
		id: part.id,
		name: part.name,
		carId: part.carId ?? '',
		reference: part.reference ?? '',
		condition: part.condition,
		category: part.category ?? '',
		location: part.location ?? '',
		quantity: part.quantity,
		cost: part.cost ?? '',
		notes: part.notes ?? '',
	}
	imageFile.value = null
	imagePreview.value = part.hasImage ? api.partImageUrl(part.id) : null
}

function cancelForm() {
	form.value = null
}

function onDialogOpenChange(isOpen) {
	if (!isOpen) {
		form.value = null
	}
}

function jpegName(name) {
	const dot = name.lastIndexOf('.')
	return (dot === -1 ? name : name.slice(0, dot)) + '.jpg'
}

function resizeImage(file) {
	return new Promise((resolve) => {
		const img = new Image()
		const objectUrl = URL.createObjectURL(file)
		img.onload = () => {
			URL.revokeObjectURL(objectUrl)
			const { width, height } = img
			if (width <= MAX_IMAGE_DIMENSION && height <= MAX_IMAGE_DIMENSION) {
				resolve(file)
				return
			}
			const scale = MAX_IMAGE_DIMENSION / Math.max(width, height)
			const canvas = document.createElement('canvas')
			canvas.width = Math.round(width * scale)
			canvas.height = Math.round(height * scale)
			canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height)
			canvas.toBlob((blob) => {
				resolve(blob ? new File([blob], jpegName(file.name), { type: 'image/jpeg' }) : file)
			}, 'image/jpeg', IMAGE_QUALITY)
		}
		img.onerror = () => {
			URL.revokeObjectURL(objectUrl)
			resolve(file)
		}
		img.src = objectUrl
	})
}

async function onImageChange(event) {
	const file = event.target.files[0]
	if (!file) {
		return
	}
	const resized = await resizeImage(file)
	imageFile.value = resized
	imagePreview.value = URL.createObjectURL(resized)
}

async function submitForm() {
	if (!form.value.name.trim()) {
		window.alert(t('carfuelmaintance', 'Please fill in the name.'))
		return
	}
	const payload = {
		name: form.value.name.trim(),
		carId: form.value.carId === '' ? null : Number(form.value.carId),
		carIdProvided: true,
		reference: form.value.reference || null,
		referenceProvided: true,
		condition: form.value.condition,
		category: form.value.category || null,
		categoryProvided: true,
		location: form.value.location || null,
		locationProvided: true,
		quantity: Number(form.value.quantity) || 1,
		cost: form.value.cost === '' ? null : Number(form.value.cost),
		costProvided: true,
		notes: form.value.notes || null,
		notesProvided: true,
	}
	try {
		let part
		if (form.value.id === null) {
			part = await api.createPart(payload)
		} else {
			part = await api.updatePart(form.value.id, payload)
		}
		if (imageFile.value) {
			await api.uploadPartImage(part.id, imageFile.value)
		}
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not save the part: {message}', { message: errorMessage(e) }))
		return
	}
	form.value = null
	imageFile.value = null
	imagePreview.value = null
	await load()
}

async function removePart(id) {
	if (!window.confirm(t('carfuelmaintance', 'Delete this part?'))) {
		return
	}
	try {
		await api.deletePart(id)
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not delete the part: {message}', { message: errorMessage(e) }))
		return
	}
	await load()
}

const dialogButtons = computed(() => [
	{ label: t('carfuelmaintance', 'Cancel'), callback: cancelForm },
	{ label: t('carfuelmaintance', 'Save'), type: 'primary', nativeType: 'submit' },
])
</script>

<template>
	<div class="parts-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewForm">{{ t('carfuelmaintance', '+ Part') }}</button>
			<label class="filter-field">
				<span>{{ t('carfuelmaintance', 'Car') }}</span>
				<select v-model="filterCarId" @change="load">
					<option value="">{{ t('carfuelmaintance', 'All') }}</option>
					<option v-for="car in carState.cars" :key="car.id" :value="car.id">{{ car.name }}</option>
				</select>
			</label>
		</div>

		<table v-if="filteredParts.length" class="entries-table">
			<thead>
				<tr>
					<th></th>
					<th>{{ t('carfuelmaintance', 'Name') }}</th>
					<th>{{ t('carfuelmaintance', 'Reference') }}</th>
					<th>{{ t('carfuelmaintance', 'Condition') }}</th>
					<th>{{ t('carfuelmaintance', 'Category') }}</th>
					<th>{{ t('carfuelmaintance', 'Location') }}</th>
					<th>{{ t('carfuelmaintance', 'Qty') }}</th>
					<th>{{ t('carfuelmaintance', 'Car') }}</th>
					<th>{{ t('carfuelmaintance', 'Cost') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="part in filteredParts" :key="part.id">
					<td class="thumb-cell">
						<img v-if="part.hasImage" :src="api.partImageUrl(part.id)" class="thumb" :alt="part.name">
					</td>
					<td>{{ part.name }}</td>
					<td>{{ part.reference || '—' }}</td>
					<td>{{ conditionLabel(part.condition) }}</td>
					<td>{{ categoryLabel(part.category) }}</td>
					<td>{{ part.location || '—' }}</td>
					<td>{{ part.quantity }}</td>
					<td>{{ carName(part.carId) }}</td>
					<td>{{ formatMoney(part.cost, currencySymbol) ?? '—' }}</td>
					<td class="row-actions">
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Edit')" :title="t('carfuelmaintance', 'Edit')" @click="openEditForm(part)">
							<Pencil :size="16" />
						</button>
						<button type="button" class="icon-btn" :aria-label="t('carfuelmaintance', 'Delete')" :title="t('carfuelmaintance', 'Delete')" @click="removePart(part.id)">
							<Delete :size="16" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>
		<p v-else class="empty">{{ t('carfuelmaintance', 'No parts yet.') }}</p>

		<NcDialog
			:open="form !== null"
			:name="form?.id === null ? t('carfuelmaintance', '+ Part') : t('carfuelmaintance', 'Edit part')"
			is-form
			size="normal"
			:buttons="dialogButtons"
			@update:open="onDialogOpenChange"
			@submit.prevent="submitForm">
			<div v-if="form" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Name') }}</span>
					<input v-model="form.name" type="text" autofocus required>
				</label>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Car') }}</span>
						<select v-model="form.carId">
							<option value="">{{ t('carfuelmaintance', 'General stock (not car-specific)') }}</option>
							<option v-for="car in carState.cars" :key="car.id" :value="car.id">{{ car.name }}</option>
						</select>
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Category (car part)') }}</span>
						<select v-model="form.category">
							<option value="">—</option>
							<option v-for="c in PART_CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
						</select>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Reference / part number') }}</span>
						<input v-model="form.reference" type="text">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Condition') }}</span>
						<select v-model="form.condition">
							<option v-for="c in CONDITIONS" :key="c" :value="c">{{ conditionLabel(c) }}</option>
						</select>
					</label>
				</div>
				<div class="dialog-row">
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Location') }}</span>
						<input v-model="form.location" type="text" :placeholder="t('carfuelmaintance', 'e.g. garage shelf 2')">
					</label>
					<label class="dialog-field">
						<span class="dialog-label">{{ t('carfuelmaintance', 'Quantity') }}</span>
						<input v-model="form.quantity" type="number" min="1" step="1">
					</label>
				</div>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Cost') }} ({{ currencySymbol }})</span>
					<input v-model="form.cost" type="number" step="0.01" min="0">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Notes') }}</span>
					<textarea v-model="form.notes" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('carfuelmaintance', 'Photo') }}</span>
					<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" @change="onImageChange">
				</label>
				<img v-if="imagePreview" :src="imagePreview" class="image-preview" alt="">
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.parts-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-bottom: 16px;
}

.filter-field {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.filter-field select {
	padding: 4px 8px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
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

.thumb-cell {
	width: 44px;
}

.thumb {
	width: 36px;
	height: 36px;
	object-fit: cover;
	border-radius: var(--border-radius);
	display: block;
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

.image-preview {
	max-width: 100%;
	max-height: 200px;
	border-radius: var(--border-radius-large);
	object-fit: contain;
}
</style>

<script setup>
import { ref, onMounted } from 'vue'
import { t } from '@nextcloud/l10n'
import api from '../api/client.js'

const reminderMonths = ref(1)
const loaded = ref(false)
const saving = ref(false)
const saved = ref(false)

function errorMessage(e) {
	return e?.response?.data?.message || e?.message || String(e)
}

onMounted(async () => {
	const settings = await api.getSettings()
	reminderMonths.value = settings.reminderMonths
	loaded.value = true
})

async function save() {
	saving.value = true
	saved.value = false
	try {
		const settings = await api.updateSettings({ reminderMonths: Number(reminderMonths.value) })
		reminderMonths.value = settings.reminderMonths
		saved.value = true
	} catch (e) {
		window.alert(t('carfuelmaintance', 'Could not save settings: {message}', { message: errorMessage(e) }))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<div class="settings-view">
		<h2>{{ t('carfuelmaintance', 'Settings') }}</h2>
		<form v-if="loaded" class="settings-form" @submit.prevent="save">
			<label class="dialog-field">
				<span class="dialog-label">{{ t('carfuelmaintance', 'Warn me this many months before a due date') }}</span>
				<input v-model="reminderMonths" type="number" min="1" max="24" step="1">
			</label>
			<p class="hint">
				{{ t('carfuelmaintance', 'Applies to maintenance reminders with a "next due" date, e.g. IUC road tax or insurance renewal — a reminder is marked "due soon" once it falls within this many months.') }}
			</p>
			<div class="actions-row">
				<button type="submit" class="save-btn" :disabled="saving">
					{{ saving ? t('carfuelmaintance', 'Saving…') : t('carfuelmaintance', 'Save') }}
				</button>
				<span v-if="saved" class="saved-hint">{{ t('carfuelmaintance', 'Saved.') }}</span>
			</div>
		</form>
	</div>
</template>

<style scoped>
.settings-view {
	padding: 24px;
	max-width: 480px;
}

.settings-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	margin-top: 16px;
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

.dialog-field input {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.hint {
	margin: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.actions-row {
	display: flex;
	align-items: center;
	gap: 12px;
}

.save-btn {
	padding: 8px 20px;
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 600;
	cursor: pointer;
}

.save-btn:disabled {
	opacity: 0.5;
	cursor: default;
}

.saved-hint {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}
</style>

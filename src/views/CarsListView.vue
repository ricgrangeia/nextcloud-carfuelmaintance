<script setup>
import { t } from '@nextcloud/l10n'
import { state } from '../store/index.js'
</script>

<template>
	<div class="empty-state">
		<h2>{{ t('carfuelmaintance', 'Car Fuel & Maintenance') }}</h2>
		<template v-if="state.loaded && state.cars.length === 0">
			<p>{{ t('carfuelmaintance', 'Add your first car using the "New car" button in the sidebar.') }}</p>
		</template>
		<p v-else>
			{{ t('carfuelmaintance', 'Select a car from the sidebar to get started.') }}
		</p>
	</div>
</template>

<style scoped>
.empty-state {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}
</style>

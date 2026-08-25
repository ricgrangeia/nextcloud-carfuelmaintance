<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import { t } from '@nextcloud/l10n'
import { state, loadCars, toggleShowArchived } from './store/index.js'
import api from './api/client.js'
import HelpButton from './components/HelpButton.vue'

const route = useRoute()
const router = useRouter()

onMounted(loadCars)

async function createCar() {
	const name = window.prompt(t('carfuelmaintance', 'New car (e.g. "Golf", "Family van")'))
	if (!name || !name.trim()) {
		return
	}
	const car = await api.createCar({ name: name.trim() })
	await loadCars()
	router.push({ name: 'overview', params: { id: car.id } })
}

function openCar(event, car) {
	event?.preventDefault?.()
	router.push({ name: 'overview', params: { id: car.id } })
}

function carDisplayName(car) {
	return car.archived
		? `${car.name} (${t('carfuelmaintance', 'archived')})`
		: car.name
}
</script>

<template>
	<NcContent app-name="carfuelmaintance">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					v-for="car in state.cars"
					:key="car.id"
					:name="carDisplayName(car)"
					:class="{ 'is-archived': car.archived }"
					:active="route.params.id === String(car.id)"
					@click="event => openCar(event, car)" />
				<NcAppNavigationItem
					:name="state.showArchived ? t('carfuelmaintance', 'Hide archived cars') : t('carfuelmaintance', 'Show archived cars')"
					@click="toggleShowArchived" />
			</template>
			<template #footer>
				<div class="nav-footer">
					<NcAppNavigationNew
						:text="t('carfuelmaintance', 'New car')"
						@click="createCar" />
					<HelpButton :title="t('carfuelmaintance', 'How Car Fuel & Maintenance works')">
						<h4>{{ t('carfuelmaintance', 'Cars') }}</h4>
						<p>{{ t('carfuelmaintance', 'Add one entry per vehicle you want to track. Set its starting odometer reading when you add it — everything else is computed from there.') }}</p>

						<h4>{{ t('carfuelmaintance', 'Fuel entries') }}</h4>
						<p>{{ t('carfuelmaintance', 'Log every fill-up with the odometer reading and quantity. Mark it "full tank" when you fill up completely — consumption is only accurate between two full-tank fill-ups, since partial fills in between are simply added to the next full one.') }}</p>

						<h4>{{ t('carfuelmaintance', 'Maintenance entries') }}</h4>
						<p>{{ t('carfuelmaintance', 'Log services, repairs and inspections. Set a "next due" date or odometer reading to get a reminder on the Overview tab as it approaches or is overdue.') }}</p>

						<h4>{{ t('carfuelmaintance', 'Overview') }}</h4>
						<p>{{ t('carfuelmaintance', 'Shows total distance, fuel and maintenance spend, average consumption, and any upcoming or overdue reminders for the selected car.') }}</p>
					</HelpButton>
				</div>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<router-view />
		</NcAppContent>
	</NcContent>
</template>

<style scoped>
:deep(.app-navigation-entry.active .app-navigation-entry__name) {
	font-weight: bold;
}

.is-archived :deep(.app-navigation-entry__name) {
	opacity: 0.6;
	font-style: italic;
}

.nav-footer {
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 0 8px;
}

.nav-footer :deep(.app-navigation-new) {
	flex: 1;
}
</style>

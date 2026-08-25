import { createRouter, createWebHashHistory } from 'vue-router'

import CarsListView from '../views/CarsListView.vue'
import CarView from '../views/CarView.vue'
import OverviewView from '../views/OverviewView.vue'
import FuelView from '../views/FuelView.vue'
import MaintenanceView from '../views/MaintenanceView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', name: 'cars', component: CarsListView },
		{
			path: '/cars/:id',
			component: CarView,
			props: true,
			children: [
				{ path: '', name: 'overview', component: OverviewView, props: true },
				{ path: 'fuel', name: 'fuel', component: FuelView, props: true },
				{ path: 'maintenance', name: 'maintenance', component: MaintenanceView, props: true },
			],
		},
	],
})

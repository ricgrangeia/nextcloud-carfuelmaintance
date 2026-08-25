import { reactive } from 'vue'
import api from '../api/client.js'

export const state = reactive({
	cars: [],
	loaded: false,
	showArchived: false,
})

export async function loadCars() {
	state.cars = await api.listCars(state.showArchived)
	state.loaded = true
}

export async function toggleShowArchived() {
	state.showArchived = !state.showArchived
	await loadCars()
}

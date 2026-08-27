import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/carfuelmaintance' + path)
const data = (promise) => promise.then((response) => response.data)

export default {
	listCars: (includeArchived = false) => data(axios.get(url('/api/cars'), { params: { includeArchived } })),
	createCar: (payload) => data(axios.post(url('/api/cars'), payload)),
	getCar: (id) => data(axios.get(url(`/api/cars/${id}`))),
	updateCar: (id, payload) => data(axios.put(url(`/api/cars/${id}`), payload)),
	deleteCar: (id) => data(axios.delete(url(`/api/cars/${id}`))),

	listFuel: (carId) => data(axios.get(url(`/api/cars/${carId}/fuel`))),
	createFuel: (carId, payload) => data(axios.post(url(`/api/cars/${carId}/fuel`), payload)),
	updateFuel: (id, payload) => data(axios.put(url(`/api/fuel/${id}`), payload)),
	deleteFuel: (id) => data(axios.delete(url(`/api/fuel/${id}`))),

	listMaintenance: (carId) => data(axios.get(url(`/api/cars/${carId}/maintenance`))),
	createMaintenance: (carId, payload) => data(axios.post(url(`/api/cars/${carId}/maintenance`), payload)),
	updateMaintenance: (id, payload) => data(axios.put(url(`/api/maintenance/${id}`), payload)),
	deleteMaintenance: (id) => data(axios.delete(url(`/api/maintenance/${id}`))),

	getSettings: () => data(axios.get(url('/api/settings'))),
	updateSettings: (payload) => data(axios.put(url('/api/settings'), payload)),
}

export const ENTRY_FUEL_TYPES = ['gasoline', 'diesel', 'lpg', 'electric', 'other']

const LABELS = {
	gasoline: (t) => t('carfuelmaintance', 'Gasoline'),
	diesel: (t) => t('carfuelmaintance', 'Diesel'),
	lpg: (t) => t('carfuelmaintance', 'LPG'),
	electric: (t) => t('carfuelmaintance', 'Electricity'),
	hybrid: (t) => t('carfuelmaintance', 'Hybrid'),
	other: (t) => t('carfuelmaintance', 'Other'),
}

export function fuelTypeLabel(t, type) {
	return LABELS[type] ? LABELS[type](t) : type
}

export const MAINTENANCE_TYPES = [
	'oil_change',
	'tires',
	'brakes',
	'battery',
	'filters',
	'inspection',
	'iuc',
	'insurance',
	'repair',
	'other',
]

const LABELS = {
	oil_change: (t) => t('carfuelmaintance', 'Oil change'),
	tires: (t) => t('carfuelmaintance', 'Tires'),
	brakes: (t) => t('carfuelmaintance', 'Brakes'),
	battery: (t) => t('carfuelmaintance', 'Battery'),
	filters: (t) => t('carfuelmaintance', 'Filters'),
	inspection: (t) => t('carfuelmaintance', 'Inspection'),
	iuc: (t) => t('carfuelmaintance', 'Road tax (IUC)'),
	insurance: (t) => t('carfuelmaintance', 'Insurance'),
	repair: (t) => t('carfuelmaintance', 'Repair'),
	other: (t) => t('carfuelmaintance', 'Other'),
}

export function maintenanceTypeLabel(t, type) {
	return LABELS[type] ? LABELS[type](t) : type
}

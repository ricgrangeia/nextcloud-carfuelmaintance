export const PART_CATEGORIES = [
	'engine',
	'brakes',
	'suspension',
	'electrical',
	'bodywork',
	'tires',
	'filters',
	'interior',
	'other',
]

const LABELS = {
	engine: (t) => t('carfuelmaintance', 'Engine'),
	brakes: (t) => t('carfuelmaintance', 'Brakes'),
	suspension: (t) => t('carfuelmaintance', 'Suspension'),
	electrical: (t) => t('carfuelmaintance', 'Electrical'),
	bodywork: (t) => t('carfuelmaintance', 'Bodywork'),
	tires: (t) => t('carfuelmaintance', 'Tires'),
	filters: (t) => t('carfuelmaintance', 'Filters'),
	interior: (t) => t('carfuelmaintance', 'Interior'),
	other: (t) => t('carfuelmaintance', 'Other'),
}

export function partCategoryLabel(t, category) {
	return LABELS[category] ? LABELS[category](t) : category
}

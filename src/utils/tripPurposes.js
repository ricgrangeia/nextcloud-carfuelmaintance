export const TRIP_PURPOSES = ['business', 'personal', 'other']

const LABELS = {
	business: (t) => t('carfuelmaintance', 'Business'),
	personal: (t) => t('carfuelmaintance', 'Personal'),
	other: (t) => t('carfuelmaintance', 'Other'),
}

export function tripPurposeLabel(t, purpose) {
	return LABELS[purpose] ? LABELS[purpose](t) : purpose
}

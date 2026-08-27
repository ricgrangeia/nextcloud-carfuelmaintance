// Currency symbol always renders after the value app-wide (e.g. "60.00 €"),
// per the user's configured symbol in Settings.
export function formatMoney(value, symbol) {
	if (value === null || value === undefined || value === '') {
		return null
	}
	return `${value} ${symbol}`
}

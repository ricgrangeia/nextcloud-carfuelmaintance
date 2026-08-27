<script setup>
import { inject } from 'vue'
import { t } from '@nextcloud/l10n'
import MiniChart from '../components/MiniChart.vue'
import { maintenanceTypeLabel } from '../utils/maintenanceTypes.js'
import { fuelTypeLabel } from '../utils/fuelTypes.js'

const { detail } = inject('carDetail')

function typeLabel(type) {
	return maintenanceTypeLabel(t, type)
}

function fuelLabel(type) {
	return fuelTypeLabel(t, type)
}

function distancePoints(history) {
	return history.map((h) => ({ date: h.date, value: h.distance }))
}

function consumptionPoints(history) {
	return history.map((h) => ({ date: h.date, value: h.consumptionPer100 }))
}

const REMINDER_LABELS = {
	overdue: () => t('carfuelmaintance', 'Overdue'),
	due_soon: () => t('carfuelmaintance', 'Due soon'),
	upcoming: () => t('carfuelmaintance', 'Upcoming'),
}

function reminderLabel(status) {
	return REMINDER_LABELS[status] ? REMINDER_LABELS[status]() : status
}

function formatDistance(remaining, unit) {
	if (remaining === null) {
		return ''
	}
	return remaining < 0
		? t('carfuelmaintance', '{n} {unit} overdue', { n: Math.abs(remaining), unit })
		: t('carfuelmaintance', 'in {n} {unit}', { n: remaining, unit })
}

function formatDays(remaining) {
	if (remaining === null) {
		return ''
	}
	return remaining < 0
		? t('carfuelmaintance', '{n} days overdue', { n: Math.abs(remaining) })
		: t('carfuelmaintance', 'in {n} days', { n: remaining })
}
</script>

<template>
	<div class="overview-view">
		<div class="stats-grid">
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Current odometer') }}</span>
				<span class="stat-value">{{ detail.stats.currentOdometer }} {{ detail.stats.odometerUnit }}</span>
			</div>
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Total distance') }}</span>
				<span class="stat-value">{{ detail.stats.totalDistance }} {{ detail.stats.odometerUnit }}</span>
			</div>
			<div v-for="group in detail.stats.consumptionByFuelType" :key="group.fuelType" class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Avg. consumption') }} ({{ fuelLabel(group.fuelType) }})</span>
				<span class="stat-value">
					<template v-if="group.avgConsumptionPer100 !== null">
						{{ group.avgConsumptionPer100 }} {{ group.unit }}/100{{ detail.stats.odometerUnit }}
					</template>
					<template v-else>—</template>
				</span>
			</div>
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Fuel spend') }}</span>
				<span class="stat-value">{{ detail.stats.totalFuelCost }}</span>
			</div>
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Maintenance spend') }}</span>
				<span class="stat-value">{{ detail.stats.totalMaintenanceCost }}</span>
			</div>
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Cost per distance') }}</span>
				<span class="stat-value">
					<template v-if="detail.stats.costPerDistance !== null">
						{{ detail.stats.costPerDistance }} / {{ detail.stats.odometerUnit }}
					</template>
					<template v-else>—</template>
				</span>
			</div>
		</div>

		<div class="reminders">
			<div class="reminders-header">
				<h3>{{ t('carfuelmaintance', 'Maintenance reminders') }}</h3>
				<router-link :to="{ name: 'settings' }" class="reminders-settings-link">
					{{ t('carfuelmaintance', 'Warns {n} month(s) ahead — change', { n: detail.stats.reminderMonths }) }}
				</router-link>
			</div>
			<p v-if="detail.stats.reminders.length === 0" class="empty">
				{{ t('carfuelmaintance', 'No upcoming reminders. Set a "next due" date or odometer on a maintenance entry to see it here.') }}
			</p>
			<ul v-else class="reminder-list">
				<li v-for="reminder in detail.stats.reminders" :key="reminder.id" :class="['reminder-item', reminder.status]">
					<span class="reminder-status">{{ reminderLabel(reminder.status) }}</span>
					<span class="reminder-type">{{ typeLabel(reminder.type) }}</span>
					<span v-if="reminder.description" class="reminder-desc">{{ reminder.description }}</span>
					<span class="reminder-due">
						<template v-if="reminder.nextDueDate">{{ reminder.nextDueDate }} ({{ formatDays(reminder.daysRemaining) }})</template>
						<template v-if="reminder.nextDueOdometer !== null">
							· {{ reminder.nextDueOdometer }} {{ detail.stats.odometerUnit }} ({{ formatDistance(reminder.distanceRemaining, detail.stats.odometerUnit) }})
						</template>
					</span>
				</li>
			</ul>
		</div>

		<div class="history">
			<h3>{{ t('carfuelmaintance', 'Consumption history') }}</h3>
			<p v-if="detail.stats.consumptionByFuelType.length === 0" class="empty">
				{{ t('carfuelmaintance', 'Not enough data yet. Log at least two full-tank fill-ups to see charts here.') }}
			</p>
			<div v-for="group in detail.stats.consumptionByFuelType" :key="group.fuelType" class="history-group">
				<h4 v-if="detail.stats.consumptionByFuelType.length > 1">{{ fuelLabel(group.fuelType) }}</h4>
				<p v-if="group.history.length === 0" class="empty">
					{{ t('carfuelmaintance', 'Not enough data yet. Log at least two full-tank fill-ups of this fuel type to see charts here.') }}
				</p>
				<template v-else>
					<div class="charts-grid">
						<div class="chart-card">
							<span class="chart-title">{{ t('carfuelmaintance', 'Distance between fill-ups') }} ({{ detail.stats.odometerUnit }})</span>
							<MiniChart type="bar" :points="distancePoints(group.history)" :unit="detail.stats.odometerUnit" />
						</div>
						<div class="chart-card">
							<span class="chart-title">{{ t('carfuelmaintance', 'Consumption over time') }} ({{ group.unit }}/100{{ detail.stats.odometerUnit }})</span>
							<MiniChart type="line" :points="consumptionPoints(group.history)" :unit="`${group.unit}/100${detail.stats.odometerUnit}`" />
						</div>
					</div>

					<table class="entries-table history-table">
						<thead>
							<tr>
								<th>{{ t('carfuelmaintance', 'Date') }}</th>
								<th>{{ t('carfuelmaintance', 'Distance') }}</th>
								<th>{{ t('carfuelmaintance', 'Fuel used') }}</th>
								<th>{{ t('carfuelmaintance', 'Consumption') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="h in group.history" :key="h.date">
								<td>{{ h.date }}</td>
								<td>{{ h.distance }} {{ detail.stats.odometerUnit }}</td>
								<td>{{ h.fuelUsed }} {{ h.unit }}</td>
								<td>{{ h.consumptionPer100 }} {{ h.unit }}/100{{ detail.stats.odometerUnit }}</td>
							</tr>
						</tbody>
					</table>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped>
.overview-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
	gap: 12px;
	margin-bottom: 32px;
}

.stat-card {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}

.stat-label {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.stat-value {
	font-size: 20px;
	font-weight: 600;
}

.reminders h3 {
	margin: 0;
}

.reminders-header {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 12px;
}

.reminders-settings-link {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	text-decoration: underline;
	white-space: nowrap;
}

.empty {
	color: var(--color-text-maxcontrast);
}

.reminder-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.reminder-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 12px;
	border-radius: var(--border-radius-large);
	border-left: 4px solid var(--color-border);
	background-color: var(--color-background-hover);
	flex-wrap: wrap;
}

.reminder-item.due_soon {
	border-left-color: #e9a23b;
}

.reminder-item.overdue {
	border-left-color: var(--color-error, #d43b3b);
}

.reminder-status {
	font-weight: 600;
	font-size: 12px;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.reminder-type {
	font-weight: 600;
}

.reminder-desc {
	color: var(--color-text-maxcontrast);
}

.reminder-due {
	margin-left: auto;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.history {
	margin-top: 32px;
}

.history h3 {
	margin: 0 0 12px;
}

.history-group {
	margin-bottom: 24px;
}

.history-group h4 {
	margin: 0 0 12px;
	font-size: 14px;
}

.charts-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
	gap: 16px;
	margin-bottom: 20px;
}

.chart-card {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 14px 16px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}

.chart-title {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.entries-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.entries-table th,
.entries-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.entries-table th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.history-table {
	max-width: 480px;
}
</style>

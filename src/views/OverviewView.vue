<script setup>
import { inject } from 'vue'
import { t } from '@nextcloud/l10n'

const { detail } = inject('carDetail')

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
			<div class="stat-card">
				<span class="stat-label">{{ t('carfuelmaintance', 'Avg. consumption') }}</span>
				<span class="stat-value">
					<template v-if="detail.stats.avgConsumptionPer100 !== null">
						{{ detail.stats.avgConsumptionPer100 }} {{ detail.stats.fuelUnit }}/100{{ detail.stats.odometerUnit }}
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
			<h3>{{ t('carfuelmaintance', 'Maintenance reminders') }}</h3>
			<p v-if="detail.stats.reminders.length === 0" class="empty">
				{{ t('carfuelmaintance', 'No upcoming reminders. Set a "next due" date or odometer on a maintenance entry to see it here.') }}
			</p>
			<ul v-else class="reminder-list">
				<li v-for="reminder in detail.stats.reminders" :key="reminder.id" :class="['reminder-item', reminder.status]">
					<span class="reminder-status">{{ reminderLabel(reminder.status) }}</span>
					<span class="reminder-type">{{ reminder.type }}</span>
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
	margin: 0 0 12px;
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
</style>

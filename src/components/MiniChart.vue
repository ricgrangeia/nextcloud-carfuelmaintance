<script setup>
import { computed } from 'vue'

const props = defineProps({
	type: { type: String, required: true }, // 'bar' | 'line'
	points: { type: Array, required: true }, // [{ date: 'YYYY-MM-DD', value: Number }]
	unit: { type: String, default: '' },
	color: { type: String, default: 'var(--color-primary-element)' },
})

const WIDTH = 600
const HEIGHT = 180
const PAD_LEFT = 36
const PAD_RIGHT = 12
const PAD_TOP = 12
const PAD_BOTTOM = 28

const plotWidth = WIDTH - PAD_LEFT - PAD_RIGHT
const plotHeight = HEIGHT - PAD_TOP - PAD_BOTTOM
const baselineY = PAD_TOP + plotHeight

const maxValue = computed(() => Math.max(...props.points.map((p) => p.value), 0))
const minValue = computed(() => (props.type === 'line' ? Math.min(...props.points.map((p) => p.value)) : 0))

function scaleY(value) {
	const range = maxValue.value - minValue.value
	if (range <= 0) {
		return baselineY
	}
	return PAD_TOP + plotHeight - ((value - minValue.value) / range) * plotHeight
}

function scaleX(index) {
	const count = props.points.length
	if (count <= 1) {
		return PAD_LEFT + plotWidth / 2
	}
	return PAD_LEFT + (index / (count - 1)) * plotWidth
}

const barWidth = computed(() => {
	const count = props.points.length || 1
	return Math.min(40, (plotWidth / count) * 0.6)
})

const linePath = computed(() => {
	return props.points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${scaleX(i).toFixed(1)} ${scaleY(p.value).toFixed(1)}`).join(' ')
})

const labelIndexes = computed(() => {
	const count = props.points.length
	if (count <= 6) {
		return props.points.map((_, i) => i)
	}
	const step = Math.ceil(count / 6)
	const indexes = []
	for (let i = 0; i < count; i += step) {
		indexes.push(i)
	}
	if (indexes[indexes.length - 1] !== count - 1) {
		indexes.push(count - 1)
	}
	return indexes
})

function shortDate(d) {
	const [, m, day] = d.split('-')
	return `${day}/${m}`
}
</script>

<template>
	<svg :viewBox="`0 0 ${WIDTH} ${HEIGHT}`" class="mini-chart" preserveAspectRatio="xMidYMid meet">
		<line :x1="PAD_LEFT" :y1="baselineY" :x2="WIDTH - PAD_RIGHT" :y2="baselineY" class="axis-line" />

		<text :x="PAD_LEFT - 6" :y="PAD_TOP + 4" class="axis-label" text-anchor="end">{{ maxValue.toFixed(0) }}</text>
		<text :x="PAD_LEFT - 6" :y="baselineY + 4" class="axis-label" text-anchor="end">{{ minValue.toFixed(0) }}</text>

		<template v-if="type === 'bar'">
			<rect
				v-for="(p, i) in points"
				:key="p.date"
				:x="scaleX(i) - barWidth / 2"
				:y="scaleY(p.value)"
				:width="barWidth"
				:height="Math.max(0, baselineY - scaleY(p.value))"
				:rx="4"
				:fill="color">
				<title>{{ p.date }}: {{ p.value }} {{ unit }}</title>
			</rect>
		</template>
		<template v-else>
			<path :d="linePath" class="line-path" :style="{ stroke: color }" fill="none" />
			<circle v-for="(p, i) in points" :key="p.date" :cx="scaleX(i)" :cy="scaleY(p.value)" r="4" :fill="color">
				<title>{{ p.date }}: {{ p.value }} {{ unit }}</title>
			</circle>
		</template>

		<text
			v-for="i in labelIndexes"
			:key="points[i].date"
			:x="scaleX(i)"
			:y="HEIGHT - 8"
			class="axis-label"
			text-anchor="middle">
			{{ shortDate(points[i].date) }}
		</text>
	</svg>
</template>

<style scoped>
.mini-chart {
	width: 100%;
	height: 180px;
	display: block;
}

.axis-line {
	stroke: var(--color-border);
	stroke-width: 1;
}

.axis-label {
	fill: var(--color-text-maxcontrast);
	font-size: 10px;
}

.line-path {
	stroke-width: 2;
	stroke-linecap: round;
	stroke-linejoin: round;
}
</style>

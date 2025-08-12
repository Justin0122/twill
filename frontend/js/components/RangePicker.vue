<template>
  <div class="range-picker">
    <label v-if="title" class="range-picker__label">{{ title }}</label>
    <div class="range-picker__slider">
      <input
        type="range"
        :min="min"
        :max="max"
        :step="step"
        :value="value"
        @input="onInput"
      />
      <span class="range-picker__value">{{ value }}</span>
    </div>
    <div class="range-picker__ticks">
      <span
        v-for="tick in ticks"
        :key="tick"
        class="range-picker__tick"
        :style="{ left: tickPosition(tick) + '%' }"
      >|</span>
    </div>
  </div>
</template>

<script>
  export default {
    name: 'A17RangePicker',
    props: {
      title: { type: String, default: '' },
      min: { type: Number, default: 0 },
      max: { type: Number, default: 100 },
      value: { type: Number, default: 0 },
      step: { type: Number, default: 1 }
    },
    computed: {
      ticks() {
        const ticks = []
        for (let i = this.min; i <= this.max; i += this.step) {
          ticks.push(i)
        }
        return ticks
      }
    },
    methods: {
      onInput(event) {
        this.$emit('input', Number(event.target.value))
      },
      tickPosition(tick) {
        return ((tick - this.min) / (this.max - this.min)) * 100
      }
    }
  }
</script>

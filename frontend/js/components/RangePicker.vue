<template>
  <div class="range-picker">
    <label v-if="title" class="range-picker__label">{{ title }}</label>
    <div class="range-picker__slider">
      <input
        type="range"
        :min="min"
        :max="max"
        :step="step"
        :value="modelValue"
        @input="onInput"
      />
      <span class="range-picker__value">{{ modelValue }}</span>
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
      modelValue: { type: Number, default: 0 },
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
        this.$emit('update:modelValue', Number(event.target.value))
      },
      tickPosition(tick) {
        return ((tick - this.min) / (this.max - this.min)) * 100
      }
    }
  }
</script>

<style scoped>
  .range-picker {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
  }
  .range-picker__label {
    font-size: 1em;
    margin-bottom: 4px;
  }
  .range-picker__slider {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .range-picker__value {
    min-width: 40px;
    text-align: center;
    font-weight: bold;
  }
  .range-picker__ticks {
    position: relative;
    height: 12px;
    margin-top: 4px;
  }
  .range-picker__tick {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    color: #888;
    font-size: 12px;
  }
</style>

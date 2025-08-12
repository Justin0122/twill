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
    <div class="range-picker__snaps">
      <span
        v-for="point in snapPoints"
        :key="point"
        class="range-picker__snap"
        :style="{ left: ((point - min) / (max - min) * 100) + '%' }"
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
    methods: {
      onInput(event) {
        this.$emit('input', Number(event.target.value))
      }
    },
    computed: {
      snapPoints() {
        const points = [];
        for (let i = this.min; i <= this.max; i += this.step) {
          points.push(i);
        }
        if (points[points.length - 1] !== this.max) {
          points.push(this.max);
        }
        return points;
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
    position: relative;
  }
  .range-picker__value {
    min-width: 40px;
    text-align: center;
    font-weight: bold;
  }
  .range-picker__snaps {
    position: relative;
    height: 10px;
    margin-top: 4px;
    width: 100%;
  }
  .range-picker__snap {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    color: #888;
    font-size: 1em;
    pointer-events: none;
  }
</style>

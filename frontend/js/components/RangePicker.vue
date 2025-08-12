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
    methods: {
      onInput(event) {
        this.$emit('update:modelValue', Number(event.target.value))
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
</style>

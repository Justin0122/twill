<script>
  export default {
    props: {
      min: { type: Number, default: 0 },
      max: { type: Number, default: 100 },
      step: { type: Number, default: 1 },
      value: { type: Number, default: 0 },
      disabled: { type: Boolean, default: false },
      required: { type: Boolean, default: false },
      note: { type: String, default: '' }
    },
    data() {
      return {
        currentValue: this.value
      }
    },
    watch: {
      value(val) {
        this.currentValue = val;
      }
    },
    methods: {
      updateValue(val) {
        this.currentValue = val;
        this.$emit('input', val);
      }
    },
    computed: {
      snapPoints() {
        const points = [];
        for (let i = this.min; i <= this.max; i += this.step) {
          points.push(i);
        }
        return points;
      }
    }
  }
</script>

<template>
  <div>
    <input
      type="range"
      :min="min"
      :max="max"
      :step="step"
      :value="currentValue"
      :disabled="disabled"
      @input="updateValue($event.target.value)"
    />
    <div>Current: {{ currentValue }}</div>
    <div class="snap-points">
      <span
        v-for="point in snapPoints"
        :key="point"
        class="snap-point"
        :style="{ left: ((point - min) / (max - min) * 100) + '%' }"
      >|</span>
    </div>
    <div v-if="note">{{ note }}</div>
  </div>
</template>

<style scoped>
  .snap-points {
    position: relative;
    height: 10px;
    margin-top: 5px;
  }
  .snap-point {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    color: #888;
  }
</style>

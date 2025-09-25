<template>
  <div class="cropper">
    <header class="cropper__header">
      <ul v-if="multiCrops" class="cropper__breakpoints">
        <li
          v-for="(crop, key, index) in cropOptions"
          :key="key"
          :class="{ 's--active': toggleBreakpoint === index }"
          @click="changeCrop(key, index)"
        >
          {{ capitalize(key) }}
        </li>
      </ul>
      <!-- Preset Ratios -->
      <ul class="cropper__presets">
        <li
          v-for="preset in presetRatios"
          :key="preset.name"
          :class="{
            's--active': currentPreset && currentPreset.name === preset.name
          }"
          @click="selectPreset(preset)"
        >
          {{ preset.name }}
        </li>
        <li v-if="currentPreset" class="cropper__clear" @click="clearPreset">
          Clear
        </li>
      </ul>
    </header>
    <div class="cropper__content">
      <div class="cropper__wrapper" ref="cropWrapper">
        <img
          class="cropper__img"
          ref="cropImage"
          :src="currentMedia.medium || currentMedia.original"
          :alt="currentMedia.name"
        />
      </div>
    </div>
    <footer class="cropper__footer">
      <ul v-if="ratiosByContext.length > 1" class="cropper__ratios">
        <li
          class="f--small"
          v-for="ratio in ratiosByContext"
          @click="changeRatio(ratio)"
          :key="ratio.name"
          :class="{ 's--active': currentRatioName === ratio.name }"
        >
          {{ capitalize(ratio.name) }}
        </li>
      </ul>
      <span
        class="cropper__values f--small hide--xsmall"
        :class="cropperWarning"
      >
        {{ cropValues.original.width }} &times; {{ cropValues.original.height }}
      </span>
      <slot></slot>
    </footer>
  </div>
</template>

<script>
  import 'cropperjs/dist/cropper.min.css'
  import CropperJs from 'cropperjs'
  import { mapState } from 'vuex'

  import cropperMixin from '@/mixins/cropper'
  import { cropConversion } from '@/utils/cropper'
  import { capitalize } from '@/utils/filters.js'

  export default {
    name: 'a17Cropper',
    props: {
      media: {
        type: Object,
        default: () => {}
      },
      context: {
        type: String,
        default: '' // listing, cover etc...
      }
    },
    mixins: [cropperMixin],
    emits: ['crop-end'],
    data() {
      const firstCropKey = Object.keys(this.media.crops || {})[0] || ''
      const firstCrop = firstCropKey
        ? this.media.crops[firstCropKey]
        : { width: 0, height: 0, name: '' }

      return {
        cropper: null,
        currentMedia: this.media,
        currentCrop: firstCropKey,
        toggleBreakpoint: 0,
        cropValues: {
          natural: { width: null, height: null },
          original: {
            width: firstCrop.width || 0,
            height: firstCrop.height || 0
          }
        },
        minCropValues: { width: 0, height: 0 },
        currentRatioName: firstCrop.name || '',
        presetRatios: [
          { name: 'Square', ratio: 1 },
          { name: 'Thumbnail', ratio: 1.5 },
          { name: 'Wide', ratio: 2 },
          { name: '3/4', ratio: 0.75 },
          { name: '4/3', ratio: 1.333 },
          { name: '16/9', ratio: 1.777 }
        ],
        currentPreset: null
      }
    },
    watch: {
      media(newMedia) {
        this.currentMedia = newMedia
      }
    },
    computed: {
      cropOptions() {
        if (this.allCrops.hasOwnProperty(this.context))
          return this.allCrops[this.context]
        return {}
      },
      crop() {
        return (
          (this.currentMedia.crops &&
            this.currentMedia.crops[this.currentCrop]) ||
          {}
        )
      },
      multiCrops() {
        return Object.keys(this.cropOptions).length > 1
      },
      ratiosByContext() {
        const filtered = this.cropOptions[this.currentCrop]
        // eslint-disable-next-line
        return filtered ? filtered : []
      },
      cropperOpts() {
        return {
          ...this.defaultCropsOpts,
          cropmove: () => {
            this.updateCropperValues()
          },
          cropend: () => {
            this.sendCropperValues()
          }
        }
      },
      cropperWarning() {
        return {
          cropper__warning:
            this.cropValues.original.width < this.minCropValues.width ||
            this.cropValues.original.height < this.minCropValues.height
        }
      },
      ...mapState({
        allCrops: state => state.mediaLibrary.crops
      })
    },
    mounted() {
      const opts = this.cropperOpts
      const imageBox = this.$refs.cropImage
      const imageWrapper = this.$refs.cropWrapper
      const img = new Image()

      img.addEventListener(
        'load',
        () => {
          imageWrapper.style.maxWidth =
            imageWrapper.getBoundingClientRect().width + 'px'
          imageWrapper.style.minHeight =
            imageWrapper.getBoundingClientRect().height + 'px'
          this.cropper = new CropperJs(imageBox, opts)
        },
        { once: true, passive: true, capture: true }
      )

      img.src = this.currentMedia.medium || this.currentMedia.original

      // init displayed crop values
      imageBox.addEventListener(
        'ready',
        () => {
          this.cropValues.natural.width = img.naturalWidth
          this.cropValues.natural.height = img.naturalHeight
          this.updateCrop()
        },
        { once: true, passive: true, capture: true }
      )
    },
    methods: {
      capitalize,

      // --- Aspect ratio selection logic
      initAspectRatio() {
        if (!this.cropper) return
        if (this.currentPreset) {
          this.minCropValues.width = 0
          this.minCropValues.height = 0
          this.cropper.setAspectRatio(this.currentPreset.ratio)
          return
        }
        const filtered = this.ratiosByContext
        const filter = filtered.find(r => r.name === this.currentRatioName)
        if (filter) {
          this.minCropValues.width = filter.minValues
            ? filter.minValues.width
            : 0
          this.minCropValues.height = filter.minValues
            ? filter.minValues.height
            : 0
          this.cropper.setAspectRatio(filter.ratio)
          return
        }
        this.cropper.setAspectRatio(NaN)
      },

      // --- UI actions
      changeCrop(cropName, index) {
        this.currentCrop = cropName
        this.currentRatioName =
          this.crop.name || this.cropOptions[cropName]?.[0]?.name || ''
        this.toggleBreakpoint = index
        this.updateCrop()
        this.sendCropperValues()
      },

      changeRatio(ratioObj) {
        if (this.currentPreset) return // ignore while a preset is active
        this.currentRatioName = ratioObj.name
        this.updateCrop()
        this.sendCropperValues()
      },

      // FIX: apply & center the preset by running full update path
      selectPreset(preset) {
        this.currentPreset = preset
        this.updateCrop() // -> initAspectRatio() + initCrop()
        this.sendCropperValues()
      },

      clearPreset() {
        this.currentPreset = null
        this.updateCrop()
        this.sendCropperValues()
      },

      // --- Update flow
      updateCrop() {
        if (!this.cropper) return
        this.initAspectRatio()
        this.initCrop()
        this.updateCropperValues()
      },

      updateCropperValues() {
        if (!this.cropper) return
        const data = this.cropper.getData(true)
        const originalCrop = this.toOriginalCrop(data)
        this.cropValues.original.width = Math.round(originalCrop.width || 0)
        this.cropValues.original.height = Math.round(originalCrop.height || 0)
      },

      // --- Position/size the crop rect
      initCrop() {
        if (!this.cropper) return

        const natural = this.cropValues.natural
        if (!natural?.width || !natural?.height) return

        if (this.currentPreset) {
          const ratio = this.currentPreset.ratio

          // Largest rect with `ratio` that fits inside the natural image
          let width = natural.width
          let height = Math.round(width / ratio)
          if (height > natural.height) {
            height = natural.height
            width = Math.round(height * ratio)
          }

          // Center it
          const x = Math.round((natural.width - width) / 2)
          const y = Math.round((natural.height - height) / 2)

          // Set piecewise to avoid CropperJS rounding bug
          this.cropper.setData({ x })
          this.cropper.setData({ y })
          this.cropper.setData({ width })
          this.cropper.setData({ height })
          return
        }

        // No preset: use saved crop (converted to natural coords)
        const crop = this.toNaturalCrop(this.crop)
        this.cropper.setData({ x: crop.x })
        this.cropper.setData({ y: crop.y })
        this.cropper.setData({ width: crop.width })
        this.cropper.setData({ height: crop.height })
      },

      // --- Utilities
      test() {
        const crop = this.toNaturalCrop({ x: 0, y: 0, width: 380, height: 475 })
        this.cropper.setAspectRatio(0.8)
        this.cropper.setData(crop)
      },

      sendCropperValues() {
        if (!this.cropper) return
        const data = { values: {} }
        data.values[this.currentCrop] = this.toOriginalCrop(
          this.cropper.getData(true)
        )
        data.values[this.currentCrop].name = this.currentRatioName
        this.$emit('crop-end', data)
      },

      toNaturalCrop(data) {
        return cropConversion(data, this.cropValues.natural, this.currentMedia)
      },
      toOriginalCrop(data) {
        return cropConversion(data, this.currentMedia, this.cropValues.natural)
      }
    },
    beforeUnmount() {
      if (this.cropper) this.cropper.destroy()
    }
  }
</script>

<style lang="scss" scoped>
  $height_li: 35px;

  .cropper {
    width: 100%;
    display: flex;
    flex-flow: column nowrap;
  }

  .cropper__content {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-grow: 1;
    height: 430px;
    background-color: $color__light;

    .cropper-modal {
      background-color: $color__light;
    }
  }

  .cropper__wrapper {
    display: block;
    height: 100%;
    margin: 0 auto;
  }

  .cropper__img {
    display: block;
    max-width: 100%;
    height: 100%;
    margin: 0 auto;
    opacity: 0;
  }

  .cropper__breakpoints {
    padding: 20px 0;
    li {
      display: inline-block;
      height: $height_li;
      line-height: $height_li;
      background-color: $color__background;
      color: $color__link;
      cursor: pointer;
      margin: 0 20px;
      border-radius: calc($height_li / 2);

      &.s--active {
        color: $color__text;
        background-color: $color__light;
        cursor: default;
        padding: 0 20px;
        margin: 0;
      }
      &:not(.s--active):hover {
        text-decoration: underline;
      }
      &:last-child {
        margin-right: 0;
      }
    }
  }

  .cropper__presets {
    padding: 10px 0;
    li {
      display: inline-block;
      margin-right: 10px;
      padding: 5px 15px;
      background: $color__background;
      border-radius: 5px;
      cursor: pointer;
      &.s--active {
        background: $color__light;
        color: $color__text;
        cursor: default;
      }
      &:hover:not(.s--active) {
        text-decoration: underline;
      }
    }
    .cropper__clear {
      display: inline-block;
      margin-left: 10px;
      padding: 5px 15px;
      background: $color__error;
      color: #fff;
      border-radius: 5px;
      cursor: pointer;
      &:hover {
        background: darken($color__error, 10%);
      }
    }
  }

  .cropper__footer {
    position: relative;
    width: 100%;

    @include breakpoint('small+') {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 75px;
    }

    .cropper__ratios {
      padding: 20px 0 0 0;
      text-align: center;

      @include breakpoint('small+') {
        padding: 20px 0;
      }

      li {
        @include font-smoothing();
        display: inline-block;
        height: $height_li;
        line-height: $height_li - 2px;
        margin-right: 15px;
        padding: 0 20px;
        background-color: transparent;
        border: 1px solid $color__border--hover;
        border-radius: 5px;
        color: $color__text--light;
        cursor: pointer;

        &:hover,
        &.s--active {
          border-color: $color__text;
          color: $color__text;
        }
        &:focus {
          border-color: $color__text;
          color: $color__text;
        }
        &:disabled {
          opacity: 0.5;
          pointer-events: none;
        }
        &:last-child {
          margin-right: 0;
        }
        &.s--active {
          cursor: default;
        }
      }
    }

    .cropper__values {
      @include font-smoothing();
      position: absolute;
      top: 50%;
      right: 0;
      color: $color__ok;
      height: $height_li;
      line-height: $height_li;
      transform: translateY(-50%);
      transition: color 250ms ease;

      &.cropper__warning {
        color: $color__error;
      }
    }
  }
</style>

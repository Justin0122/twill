<template>
  <div
    class="media"
    :class="{ 'media--hoverable': hover, 'media--slide': isSlide }"
  >
    <div class="media__field">
      <div class="media__info" v-if="hasMedia">
        <div class="media__img">
          <div class="media__imgFrame" @click="openMediaLibrary(1, mediaKey, index)">
            <div class="media__imgCentered" :style="cropThumbnailStyle">
              <img
                v-if="cropSrc && showImg"
                :src="cropSrc"
                ref="mediaImg"
                :class="cropThumbnailClass"
              />
            </div>
            <div class="media__edit" v-if="!disabled">
              <span class="media__edit--button"
              ><span v-svg symbol="edit"></span
              ></span>
            </div>
          </div>
        </div>

        <!-- Two-column meta (left: details, right: references) -->
        <div class="media__meta" v-if="!disabled">
          <!-- Left: details -->
          <ul class="media__metadatas">
            <li class="media__name" @click="openMediaLibrary(1, mediaKey, index)">
              <strong :title="media.name">{{ media.name }}</strong>
            </li>
            <li class="f--small" v-if="media.size">
              File size: {{ uppercase(media.size) }}
            </li>
            <li class="f--small" v-if="media.width + media.height">
              {{ $trans('fields.medias.original-dimensions') }}:
              {{ media.width }}&nbsp;&times;&nbsp;{{ media.height }}
            </li>
            <li
              class="f--small media__crop-link"
              v-if="cropInfos && activeCrop"
              @click="openCropMedia"
            >
              <p
                class="f--small f--note hide--xsmall"
                v-for="(cropInfo, index) in cropInfos"
                :key="index"
              >
                <span v-html="cropInfo"></span>
              </p>
            </li>
            <li class="f--small">
              <a
                href="#"
                @click.prevent="metadatasInfos"
                v-if="withAddInfo"
                class="f--link-underlined--o"
              >{{ metadatas.text }}</a>
            </li>
          </ul>

          <aside class="media__refs" v-if="shouldShowRefs">
            <ul class="media__references">
              <li class="media__name">
                {{ $trans('media-library.sidebar.references', 'References') }}
              </li>

              <li v-if="ownersCount === 0" class="f--tiny f--note">
                {{
                  $trans('media-library.sidebar.no-references', 'No references')
                }}
              </li>

              <li
                v-for="mod in moduleKeys"
                :key="'mod_' + mod"
                class="owners-module"
              >
                <button
                  type="button"
                  class="module-toggle"
                  @click="toggleModule(mod)"
                >
                  <span class="chev">{{ isModuleOpen(mod) ? '▼' : '►' }}</span>
                  <span class="module-badge">{{ mod }}</span>
                  <span class="count">({{ groupedOwners[mod].length }})</span>
                </button>

                <ul
                  v-show="isModuleOpen(mod)"
                  class="ownerslist"
                  :class="{ 'owners--scroll': groupedOwners[mod].length > 8 }"
                >
                  <li
                    class="f--small"
                    v-for="(item, index) in groupedOwners[mod]"
                    :key="'mediaowner_' + (item.id ?? index)"
                  >
                    <a class="mediaowner_link"
                       v-if="item.edit || item.admin_url"
                       :href="item.edit || item.admin_url"
                       target="_blank"
                    >
                      {{
                        item.title ||
                        item.name ||
                        ((item.type || 'Item') + ' #' + (item.id ?? '?'))
                      }}
                    </a>
                    <span v-else>
                      {{
                        item.title ||
                        item.name ||
                        (item.type || 'Item') + ' #' + (item.id ?? '?')
                      }}
                    </span>
                  </li>
                </ul>
              </li>
            </ul>
          </aside>
        </div>
        <!-- Action buttons (dropdown) -->
        <div class="media__actions-dropDown" v-if="!disabled">
          <a17-dropdown ref="dropDown" position="right">
            <a17-button
              size="icon"
              variant="icon"
              class="media__actions-trigger"
              @click="$refs.dropDown?.toggle?.()"
              aria-label="More actions"
            >
              <span v-svg symbol="more-dots"></span>
            </a17-button>

            <template #dropdown__content>
              <div class="dropdownActions">
                <a
                  :href="media.original"
                  download
                  @click="$refs.dropDown?.close?.()"
                >
                  <span v-svg symbol="download"></span>
                  {{ $trans('fields.medias.download') }}
                </a>

                <button
                  type="button"
                  v-if="activeCrop"
                  @click="handleCropClick"
                >
                  <span v-svg symbol="crop"></span>
                  {{ $trans('fields.medias.crop') }}
                </button>

                <button type="button" @click="handleDeleteClick">
                  <span v-svg symbol="trash"></span>
                  {{ $trans('fields.medias.delete') }}
                </button>
              </div>
            </template>
          </a17-dropdown>
        </div>
      </div>

      <!--Add media button-->
      <a17-button
        variant="ghost"
        @click="openMediaLibrary"
        :disabled="disabled"
        v-if="!hasMedia"
      >{{ btnLabel }}</a17-button
      >
      <p class="media__note f--small" v-if="!!this.$slots.default">
        <slot />
      </p>

      <!-- Metadatas options -->
      <div
        class="media__metadatas--options"
        :class="{ 's--active': metadatas.active }"
        v-if="hasMedia && withAddInfo"
      >
        <a17-mediametadata
          :name="metadataName"
          :label="$trans('fields.medias.alt-text', 'Alt Text')"
          id="altText"
          :media="media"
          :maxlength="altTextMaxLength"
          @change="updateMetadata"
        />

        <a17-mediametadata
          v-if="withCaption"
          :wysiwyg="useWysiwyg"
          :wysiwyg-options="wysiwygOptions"
          type="text"
          :name="metadataName"
          :label="$trans('fields.medias.caption', 'Caption')"
          id="caption"
          :media="media"
          :maxlength="captionMaxLength"
          @change="updateMetadata"
        />

        <a17-mediametadata
          v-if="withVideoUrl"
          :name="metadataName"
          :label="$trans('fields.medias.video-url', 'Video URL (optional)')"
          id="video"
          :media="media"
          @change="updateMetadata"
        />

        <template v-for="field in extraMetadatas">
          <a17-mediametadata
            v-if="extraMetadatas.length > 0"
            :key="field.name"
            :type="field.type"
            :name="metadataName"
            :wysiwyg="field.wysiwyg || false"
            :wysiwyg-options="field.wysiwygOptions || wysiwygOptions"
            :label="field.label"
            :id="field.name"
            :media="media"
            :maxlength="field.maxlength || 0"
            @change="updateMetadata"
          />
        </template>
      </div>
    </div>

    <!-- Crop modal -->
    <a17-modal
      class="modal--cropper"
      :ref="cropModalName"
      :forceClose="true"
      :title="$trans('fields.medias.crop-edit')"
      mode="medium"
      v-if="hasMedia && activeCrop"
    >
      <a17-cropper
        :media="media"
        v-on:crop-end="cropMedia"
        :aspectRatio="16 / 9"
        :context="cropContext"
        :key="cropperKey"
      >
        <a17-button
          class="cropper__button"
          variant="action"
          @click="$refs[cropModalName].close()"
        >{{ $trans('fields.medias.crop-save') }}</a17-button
        >
      </a17-cropper>
    </a17-modal>
    <input :name="inputName" type="hidden" :value="JSON.stringify(media)" />
  </div>
</template>

<script>
  import smartCrop from 'smartcrop'
  import { mapState } from 'vuex'

  import a17Cropper from '@/components/Cropper.vue'
  import a17MediaMetadata from '@/components/MediaMetadata.vue'
  import mediaFieldMixin from '@/mixins/mediaField.js'
  import mediaLibrayMixin from '@/mixins/mediaLibrary/mediaLibrary.js'
  import { MEDIA_LIBRARY } from '@/store/mutations'
  import { cropConversion } from '@/utils/cropper'
  import { uppercase } from '@/utils/filters.js'

  const IS_SAFARI =
    navigator.userAgent.indexOf('Safari') !== -1 &&
    navigator.userAgent.indexOf('Chrome') === -1

  export default {
    name: 'A17Mediafield',
    components: {
      'a17-cropper': a17Cropper,
      'a17-mediametadata': a17MediaMetadata
    },
    mixins: [mediaLibrayMixin, mediaFieldMixin],
    props: {
      name: { type: String, required: true },
      disabled: { type: Boolean, default: false },
      required: { type: Boolean, default: false },
      btnLabel: {
        type: String,
        default() {
          return window.$trans('fields.medias.btn-label', 'Attach image')
        }
      },
      hover: { type: Boolean, default: false },
      isSlide: { type: Boolean, default: false },
      index: { type: Number, default: 0 }, // Index of media in selected context
      mediaContext: { type: String, default: '' }, // current media context
      activeCrop: { type: Boolean, default: true },
      widthMin: { type: Number, default: 0 },
      heightMin: { type: Number, default: 0 }
    },
    data: function() {
      return {
        canvas: null,
        img: null,
        ctx: null,
        imgLoaded: false,
        cropSrc: '',
        showImg: false,
        isDestroyed: false,
        naturalDim: { width: null, height: null },
        originalDim: { width: null, height: null },
        hasMediaChanged: false,
        metadatas: {
          text: this.$trans('fields.medias.edit-info'),
          textOpen: this.$trans('fields.medias.edit-info'),
          textClose: this.$trans('fields.medias.edit-close'),
          active: false
        },
        ownersExpandedModules: Object.create(null) // { [module]: boolean }
      }
    },
    computed: {
      ...mapState({
        useWysiwyg: state => state.mediaLibrary.config.useWysiwyg,
        wysiwygOptions: state => state.mediaLibrary.config.wysiwygOptions,
        selectedMedias: state => state.mediaLibrary.selected,
        allCrops: state => state.mediaLibrary.crops,
        showMediaReferences: state => {
          const cfg = state.mediaLibrary && state.mediaLibrary.config
          return cfg && typeof cfg.showMediaReferences === 'boolean'
            ? cfg.showMediaReferences
            : true
        }
      }),
      shouldShowRefs() {
        return this.hasMedia && this.showMediaReferences
      },
      groupedOwners() {
        return this.groupByModule(this.safeOwners)
      },
      moduleKeys() {
        return Object.keys(this.groupedOwners).sort()
      },
      cropThumbnailStyle: function() {
        if (this.showImg) return {}
        if (!this.hasMedia) return {}
        if (!this.media.crops) return {}
        if (this.cropSrc.length === 0) return {}
        return { backgroundImage: `url(${this.cropSrc})` }
      },
      safeOwners() {
        const top = Array.isArray(this.media?.owners) ? this.media.owners : null
        const def = Array.isArray(this.media?.metadatas?.default?.owners)
          ? this.media.metadatas.default.owners
          : null
        const cus = Array.isArray(this.media?.metadatas?.custom?.owners)
          ? this.media.metadatas.custom.owners
          : null
        return top || def || cus || []
      },
      ownersCount() {
        return this.safeOwners.length
      },
      cropThumbnailClass: function() {
        if (!this.hasMedia) return {}
        if (!this.media.crops) return {}
        const crop = this.media.crops[Object.keys(this.media.crops)[0]]
        return {
          'media__img--landscape': crop.width / crop.height >= 1,
          'media__img--portrait': crop.width / crop.height < 1
        }
      },
      mediaKey: function() {
        return this.mediaContext.length > 0 ? this.mediaContext : this.name
      },
      inputName: function() {
        let fieldName = this.name
        if (this.name.indexOf('[')) {
          fieldName = this.name.replace(']', '').replace('[', '][')
        }
        return 'medias[' + fieldName + '][' + this.index + ']'
      },
      metadataName: function() {
        return 'mediaMeta[' + this.name + '][' + this.media.id + ']'
      },
      media: function() {
        if (this.selectedMedias.hasOwnProperty(this.mediaKey)) {
          return this.selectedMedias[this.mediaKey][this.index] || {}
        } else {
          return {}
        }
      },
      cropInfos: function() {
        const cropInfos = []
        if (this.media.crops) {
          for (const variant in this.media.crops) {
            if (
              this.media.crops[variant].width + this.media.crops[variant].height
            ) {
              // crop is not 0x0
              let cropInfo = ''
              cropInfo +=
                this.media.crops[variant].name +
                ' ' +
                this.$trans('fields.medias.crop-list') +
                ': '
              cropInfo +=
                this.media.crops[variant].width +
                '&nbsp;&times;&nbsp;' +
                this.media.crops[variant].height
              cropInfos.push(cropInfo)
            }
          }
        }
        return cropInfos.length > 0 ? cropInfos : null
      },
      hasMedia: function() {
        return Object.keys(this.media).length > 0
      },
      cropperKey: function() {
        return `${this.mediaKey}-${this.index}_${this.cropContext}`
      },
      mediaHasCrop: function() {
        return this.media.crops
      },
      cropModalName: function() {
        return `${this.name}Modal`
      }
    },
    watch: {
      media: function(val, oldVal) {
        this.hasMediaChanged = val !== oldVal
        if (this.selectedMedias.hasOwnProperty(this.mediaKey)) {
          // reset isDestroyed status because we changed the media
          if (this.selectedMedias[this.mediaKey][this.index])
            this.isDestroyed = false
        }
      }
    },
    methods: {
      handleCropClick() {
        this.openCropMedia()
        this.$refs.dropDown?.close?.()
      },
      handleDeleteClick() {
        this.deleteMediaClick()
        this.$refs.dropDown?.close?.()
      },
      uppercase,
      // crop
      canvasCrop() {
        const data = this.media.crops[Object.keys(this.media.crops)[0]]
        if (!data) return

        // in case of a 0x0 crop : let's display the full image in the preview
        if (data.width + data.height === 0) {
          this.showDefaultThumbnail()
          return
        }

        // default src
        let src = this.media.thumbnail

        this.$nextTick(() => {
          try {
            const crop = cropConversion(data, this.naturalDim, this.originalDim)
            const cropWidth = crop.width
            const cropHeight = crop.height
            this.canvas.width = cropWidth
            this.canvas.height = cropHeight
            this.ctx.drawImage(
              this.img,
              crop.x,
              crop.y,
              cropWidth,
              cropHeight,
              0,
              0,
              cropWidth,
              cropHeight
            )
            src = this.canvas.toDataURL('image/png')

            // show data url in the background
            if (this.cropSrc !== src) {
              this.showImg = false
              this.cropSrc = src
            }
          } catch (error) {
            // eslint-disable-next-line no-console
            console.error(error)

            // fallback on displaying the thumbnail
            if (this.cropSrc !== src) {
              this.showImg = true
              this.cropSrc = src
            }
          }
        })
      },
      setDefaultCrops: function() {
        const defaultCrops = {}
        const smarcrops = []

        if (this.allCrops.hasOwnProperty(this.cropContext)) {
          for (const cropVariant in this.allCrops[this.cropContext]) {
            const ratio = this.allCrops[this.cropContext][cropVariant][0].ratio
            const width = this.media.width
            const height = this.media.height
            const center = { x: width / 2, y: height / 2 }

            let cropWidth = width
            let cropHeight = height

            if (ratio > 0 && ratio < 1) {
              // "portrait" crop
              cropWidth = Math.floor(Math.min(height * ratio, width))
              cropHeight = Math.floor(cropWidth / ratio)
            } else if (ratio >= 1) {
              // "landscape" or square crop
              cropHeight = Math.floor(Math.min(width / ratio, height))
              cropWidth = Math.floor(cropHeight * ratio)
            }

            let crop = { x: 0, y: 0, width: cropWidth, height: cropHeight }

            // Convert crop for original img values
            crop = cropConversion(crop, this.naturalDim, this.originalDim)

            smarcrops.push(
              smartCrop.crop(this.img, {
                width: crop.width,
                height: crop.height,
                minScale: 1.0
              })
            )

            const x = Math.floor(center.x - cropWidth / 2)
            const y = Math.floor(center.y - cropHeight / 2)

            defaultCrops[cropVariant] = {}
            defaultCrops[cropVariant].name =
              this.allCrops[this.cropContext][cropVariant][0].name ||
              cropVariant
            defaultCrops[cropVariant].x = x
            defaultCrops[cropVariant].y = y
            defaultCrops[cropVariant].width = cropWidth
            defaultCrops[cropVariant].height = cropHeight
          }

          Promise.all(smarcrops).then(
            values => {
              let index = 0
              values.forEach(value => {
                const topCrop = {
                  x: value.topCrop.x,
                  y: value.topCrop.y,
                  width: value.topCrop.width,
                  height: value.topCrop.height
                }
                // Restore crop natural values (aka: value to store)
                const cropVariant =
                  defaultCrops[Object.keys(defaultCrops)[index]]
                const crop = cropConversion(
                  topCrop,
                  this.originalDim,
                  this.naturalDim
                )
                cropVariant.x = crop.x
                cropVariant.y = crop.y
                cropVariant.width = crop.width
                cropVariant.height = crop.height
                index++
              })
              this.cropMedia({ values: defaultCrops })
            },
            error => {
              // eslint-disable-next-line no-console
              console.error(error)
              this.cropMedia({ values: defaultCrops })
            }
          )
        } else {
          this.cropMedia({ values: defaultCrops })
        }
      },
      cropMedia: function(crop) {
        crop.key = this.mediaKey
        crop.index = this.index
        this.$store.commit(MEDIA_LIBRARY.SET_MEDIA_CROP, crop)
        if (this.img) this.canvasCrop()
      },
      setNaturalDimensions: function() {
        if (this.img) {
          this.naturalDim.width = this.img.naturalWidth
          this.naturalDim.height = this.img.naturalHeight
        }
      },
      setOriginalDimensions: function() {
        if (this.media) {
          this.originalDim.width = this.media.width
          this.originalDim.height = this.media.height
        }
      },
      init: function() {
        this.showImg = false

        const imgLoaded = () => {
          this.setNaturalDimensions()
          this.setOriginalDimensions()

          if (!this.mediaHasCrop) {
            this.setDefaultCrops()
          } else {
            this.canvasCrop()
          }
        }

        if (this.hasMedia) {
          this.cropSrc = this.media.thumbnail

          this.initImg().then(
            () => {
              imgLoaded()
            },
            error => {
              // eslint-disable-next-line no-console
              console.error(error)
              this.showDefaultThumbnail()

              // lets try to load to image tag now
              this.$nextTick(() => {
                // the image tag
                const imgTag = this.$refs.mediaImg
                if (imgTag) {
                  imgTag.addEventListener(
                    'load',
                    () => {
                      this.img = imgTag
                      imgLoaded()
                    },
                    {
                      once: true,
                      passive: true,
                      capture: true
                    }
                  )

                  imgTag.addEventListener('error', e => {
                    // eslint-disable-next-line no-console
                    console.error(e)
                    this.showDefaultThumbnail()
                  })
                } else {
                  this.showImg = false
                  this.cropSrc = this.media.thumbnail
                }
              })
            }
          )
          this.hasMediaChanged = false
        }
      },
      initImg: function() {
        return new Promise((resolve, reject) => {
          this.img = new Image()
          if (!IS_SAFARI) {
            this.img.crossOrigin = 'Anonymous'
          }
          this.canvas = document.createElement('canvas')
          this.ctx = this.canvas.getContext('2d')

          this.img.addEventListener(
            'load',
            () => {
              resolve()
            },
            {
              once: true,
              passive: true,
              capture: true
            }
          )

          // in case of CORS issue or anything else
          this.img.addEventListener('error', e => {
            reject(e)
          })

          // try to load the media thumbnail
          let append = '?'
          if (this.media.thumbnail.indexOf('?') > -1) {
            append = '&'
          }
          this.img.src = this.media.thumbnail + append + 'no-cache'
        })
      },
      showDefaultThumbnail: function() {
        this.showImg = true
        if (this.hasMedia) this.cropSrc = this.media.thumbnail
      },
      openCropMedia: function() {
        this.$refs[this.cropModalName].open()
      },
      deleteMediaClick: function() {
        this.isDestroyed = true
        this.deleteMedia()
      },
      // delete the media
      deleteMedia: function() {
        this.$store.commit(MEDIA_LIBRARY.DESTROY_SPECIFIC_MEDIA, {
          name: this.mediaKey,
          index: this.index
        })
      },
      // metadatas
      updateMetadata: function(newValue) {
        this.$store.commit(MEDIA_LIBRARY.SET_MEDIA_METADATAS, {
          media: { context: this.mediaKey, index: this.index },
          value: newValue
        })
      },
      metadatasInfos: function() {
        this.metadatas.active = !this.metadatas.active
        this.metadatas.text = this.metadatas.active
          ? this.metadatas.textClose
          : this.metadatas.textOpen
      },
      destroyValue: function() {
        if (this.isSlide) return // for Slideshows : medias are deleted with slideshow component
        if (!this.isDestroyed) this.deleteMedia()
      },

      // --- references grouping / toggling ---
      groupByModule(list) {
        return (list || []).reduce((acc, item) => {
          const mod = item?.module || item?.type || 'other'
          if (!acc[mod]) acc[mod] = []
          acc[mod].push(item)
          return acc
        }, {})
      },
      isModuleOpen(mod) {
        // default CLOSED; flip to `|| true` if you want open-by-default
        return !!this.ownersExpandedModules[mod]
      },
      toggleModule(mod) {
        const current = !!this.ownersExpandedModules[mod]
        if (this.$set) this.$set(this.ownersExpandedModules, mod, !current)
        else this.ownersExpandedModules[mod] = !current
      }
    },
    beforeMount: function() {
      this.init()
    },
    beforeUpdate: function() {
      if (this.hasMediaChanged) {
        this.init()
      }
    }
  }
</script>

<style lang="scss" scoped>
  $input-bg: #fcfcfc;
  $input-border: #dfdfdf;
  $height_input: 45px;

  .media {
    border-radius: 2px;
    border: 1px solid $color__border;
    background: $color__background;
  }

  .media__field {
    // height:$height_input + 2px;
    padding: 10px;
    position: relative;
    /*overflow-x: hidden;*/
  }

  .media--slide {
    border: 0 none;
  }

  .media__note {
    color: $color__text--light;
    float: right;
    position: absolute;
    bottom: 18px;
    right: 15px;
    display: none;

    @include breakpoint('small+') {
      display: inline-block;
    }

    @include breakpoint('medium') {
      display: none;
    }

    .s--in-editor & {
      @include breakpoint('small+') {
        display: none;
      }
    }
  }
  @media (max-width: 1024px) {
    .media__img {
      flex-basis: 200px;
      width: 200px;
      min-width: 200px;
      max-width: 200px;
    }
  }
  @media (max-width: 640px) {
    .media__img {
      flex-basis: 160px;
      width: 160px;
      min-width: 160px;
      max-width: 160px;
    }
  }
  .media__img {
    flex: 0 0 240px;
    width: 240px;
    min-width: 240px;
    max-width: 240px;
    transition: filter 0.15s ease;

    &:hover {
      cursor: pointer;
      filter: brightness(90%);
    }

    &:before {
      content: '';
      position: absolute;
      display: block;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    img {
      display: block;
      max-width: 100%;
      max-height: 100%;
      margin: auto;

      &.media__img--landscape {
        width: 100%;
        height: auto;
      }

      &.media__img--portrait {
        width: auto;
        height: 100%;
      }
    }
  }
  /* keep the preview from collapsing vertically */
  .media__img {
    position: relative;
    flex: 0 0 240px;
    width: 240px;
    min-width: 240px;
    max-width: 240px;
  }

  .media__imgFrame {
    width: 100%;
    padding-bottom: 100%;
    position: relative;
    overflow: hidden;
  }

  @media (max-width: 1024px) {
    .media__img {
      flex-basis: 200px;
      width: 200px;
      min-width: 200px;
      max-width: 200px;
    }
    .media__imgFrame {
      min-height: 170px;
    }
  }
  @media (max-width: 640px) {
    .media__img {
      flex-basis: 160px;
      width: 160px;
      min-width: 160px;
      max-width: 160px;
    }
    .media__imgFrame {
      min-height: 140px;
    }
  }

  .media__meta {
    padding: 5px 15px;
    flex-grow: 1;
    color: $color__text--light;
    overflow: hidden;
    min-width: 0;

    display: grid;
    grid-template-columns: 1fr minmax(220px, 38%);
    gap: 16px;
    align-items: start;
  }

  @media (max-width: 1024px) {
    .media__meta {
      grid-template-columns: 1fr;
    }
  }

  /* tighten spacing on the right column */
  .media__refs .media__references {
    margin-top: 0;
  }

  .media__refs {
    min-width: 220px;
  }

  .media__info {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: flex-start;
    align-content: flex-start;
  }

  .media__metadatas {
    color: $color__text--light;

    li {
      overflow: hidden;
    }

    a {
      color: $color__link;
    }
  }

  .media__name {
    strong {
      font-weight: normal;
      color: $color__text;
      overflow: hidden;
      text-overflow: ellipsis;
      display: block;
      margin-bottom: 5px;
      // white-space: nowrap;
    }

    &:hover {
      cursor: pointer;

      strong {
        color: $color__link;
      }
    }
  }

  .media__metadatas--options {
    display: none;
    margin-top: 35px;
  }

  .media__metadatas--options.s--active {
    display: block;
  }

  .media__actions {
    min-width: 45px * 3;

    @media screen and (max-width: 1140px) {
      display: none !important;
    }

    .s--in-editor & {
      display: none !important;
    }
  }

  .media__actions-dropDown {
    display: flex;
    justify-content: flex-end;
    align-items: flex-start;
    margin-left: auto;
    position: relative;

    .media__actions-trigger {
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .dropdownActions {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 160px;
      padding: 8px;
    }

    a,
    button {
      display: flex;
      align-items: center;
      gap: 6px;
      width: 100%;
      text-align: left;
      background: transparent;
      border: 0;
      color: $color__text;
      font: inherit;
      cursor: pointer;
      padding: 4px 6px;

      &:hover {
        background-color: rgba(0, 0, 0, 0.05);
      }
    }

    .media__actions-trigger {
      width: 26px;
    }
  }

  .media__meta .media__actions-dropDown {
    grid-column: 2;
    justify-self: end;
  }

  /* Modal with cropper */
  .modal--cropper .cropper__button {
    width: 100%;
    display: block;
    margin-top: 20px;
    margin-bottom: 20px;

    @include breakpoint('small+') {
      position: absolute;
      bottom: 0;
      left: 0;
      width: auto;
      margin-top: 20px;
      margin-bottom: 20px;
    }
  }

  .media__references {
    margin-top: 20px;
  }
  .media__refs .media__references {
    margin-top: 0;
  }

  .owners--scroll {
    max-height: 180px;
    overflow: auto;
    padding-right: 4px;
  }
  .owners-toggle {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: $color__link;
    font: inherit;
  }

  .module-badge {
    display: inline-block;
    font-size: 11px;
    line-height: 1;
    padding: 2px 6px;
    margin-right: 6px;
    border: 1px solid $color__border;
    border-radius: 10px;
    color: $color__text--light;
    transition: background-color 0.15s ease, color 0.15s ease;
  }

  .owners-module {
    margin-top: 8px;
  }

  .module-toggle {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: $color__text;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font: inherit;

    &:hover {
      border-bottom: 1px dashed $color__text--light;
      .module-badge {
        background-color: $color__link;
        color: #fff;
        opacity: 0.7;
      }
    }
  }
  .module-toggle .chev {
    width: 1em;
    display: inline-block;
    text-align: center;
  }
  .module-toggle .count {
    color: $color__text--light;
    font-size: 12px;
  }

  .ownerslist {
    margin: 6px 0 0 22px;
    padding: 0;
    list-style: none;
  }
  .media__imgFrame {
    aspect-ratio: 1 / 1;
    padding-bottom: 0;
  }
  @media (max-width: 1024px) {
    .media__imgFrame {
      min-height: 200px;
    }
  }
  @media (max-width: 640px) {
    .media__imgFrame {
      min-height: 160px;
    }
  }

  .media__imgCentered {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    background-color: $color__lighter;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center center;
  }

  .media__imgCentered img {
    width: auto !important;
    height: auto !important;
    max-width: 100%;
    max-height: 100%;
    margin: auto;
    object-fit: contain;
  }

  .media__crop-link:hover {
    cursor: pointer;
    text-decoration: underline;
  }

  .mediaowner_link {
    color: $color__link;
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }
</style>

<style lang="scss">
  .media .media__actions-dropDown .dropdown__content {
    margin-top: 10px;
  }
</style>

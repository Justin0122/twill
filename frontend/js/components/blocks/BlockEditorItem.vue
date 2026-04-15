<template>
  <div class="block" :class="blockClasses">
    <div class="block__header" @dblclick.prevent="toggleExpand()">
      <div class="block__toggle">
        <a17-dropdown
          :ref="moveDropdown"
          class="f--small"
          position="bottom-left"
          v-if="withMoveDropdown && withActions"
          :maxHeight="270"
        >
          <span
            class="block__counter f--tiny"
            @click="$refs[moveDropdown].toggle()"
            >{{ index + 1 }}</span
          >
          <div slot="dropdown__content">
            <slot name="dropdown-numbers" />
          </div>
        </a17-dropdown>
        <span class="block__counter f--tiny" v-else-if="withActions">{{
          index + 1
        }}</span>
        <span class="block__title">
          <span
            v-for="(part, partIndex) in blockTitleParts"
            :key="`title-part-${partIndex}`"
            class="block__titlePart"
          >
            <span v-if="partIndex > 0" class="block__titleSeparator">— </span>
            <span v-if="part.type === 'text'">{{ part.value }}</span>
            <img
              v-else-if="part.type === 'media'"
              class="block__titleImage"
              :src="part.thumbnail"
              :alt="part.alt || ''"
            />
            <a
              v-else-if="part.type === 'browser' && part.href"
              class="block__titleLink f--link-underlined--o"
              :href="part.href"
              target="_blank"
              rel="noopener noreferrer"
            >
              {{ part.value
              }}<span v-if="part.repository"> - {{ part.repository }}</span>
            </a>
            <span
              v-else-if="part.type === 'browser'"
              class="block__titleBrowserText"
            >
              {{ part.value
              }}<span v-if="part.repository"> - {{ part.repository }}</span>
            </span>
          </span>
        </span>
      </div>
      <div class="block__right" v-if="withActions || withHandle">
        <span v-if="withHandle" class="block__handle"></span>
        <div class="block__actions" v-if="withActions">
          <slot name="block-actions" />
          <a17-dropdown
            :ref="addDropdown"
            position="bottom-right"
            :maxHeight="430"
            @open="hover = true"
            @close="hover = false"
            v-if="withAddDropdown"
          >
            <a17-button
              variant="icon"
              data-action
              @click="$refs[addDropdown].toggle()"
              ><span v-svg symbol="add"></span>
            </a17-button>
            <div slot="dropdown__content">
              <slot name="dropdown-add" />
            </div>
          </a17-dropdown>

          <a17-button
            variant="icon"
            data-action
            @click="toggleExpand()"
            :aria-expanded="visible ? 'true' : 'false'"
            ><span v-svg symbol="expand"></span
          ></a17-button>

          <a17-dropdown
            :ref="actionsDropdown"
            position="bottom-right"
            @open="hover = true"
            @close="hover = false"
          >
            <a17-button variant="icon" @click="$refs[actionsDropdown].toggle()"
              ><span v-svg symbol="more-dots"></span>
            </a17-button>
            <div slot="dropdown__content">
              <slot name="dropdown-action" />
            </div>
          </a17-dropdown>
        </div>
      </div>
    </div>
    <div class="block__content" v-if="visible">
      <component
        v-bind:is="`${block.type}`"
        :name="componentName(block.id)"
        v-bind="block.attributes"
        :key="`form_${block.type}_${block.id}`"
      >
        <!-- dynamic components -->
      </component>
      <!-- Block validation input frame, to display errors -->
      <a17-inputframe
        size="small"
        label=""
        :name="`block.${block.id}`"
      ></a17-inputframe>
    </div>
  </div>
</template>

<script>
  import { mapGetters, mapState } from 'vuex'

  import a17VueFilters from '@/utils/filters.js'

  export default {
    name: 'A17BlockEditorItem',
    props: {
      index: {
        type: Number,
        default: 0
      },
      opened: {
        type: Boolean,
        default: true
      },
      size: {
        type: String,
        default: '' // small
      },
      block: {
        type: Object,
        default: () => {}
      },
      withHandle: {
        type: Boolean,
        default: true
      },
      withActions: {
        type: Boolean,
        default: true
      }
    },
    data() {
      return {
        visible: false,
        hover: false,
        withMoveDropdown: true,
        withAddDropdown: true
      }
    },
    filters: a17VueFilters,
    computed: {
      blockTitleParts: function() {
        const titleFields = Array.isArray(this.block.titleField)
          ? this.block.titleField
          : [this.block.titleField]
        const parts = []

        if (!this.block.hideTitlePrefix && this.block.title) {
          parts.push({
            type: 'text',
            value: this.block.title
          })
        }

        titleFields.forEach(field => {
          const { name, crop } = this.normalizeTitleField(field)
          if (!name) return

          const part = this.formatTitleFieldValue(
            this.titleFieldValue(name),
            crop
          )
          if (part) parts.push(part)
        })

        if (this.block.hideTitlePrefix && !parts.length && this.block.title) {
          parts.push({
            type: 'text',
            value: this.block.title
          })
        }

        return parts
      },
      blockClasses() {
        return [
          this.visible ? 'block--open' : '',
          this.hover ? 'block--focus' : '',
          this.size ? `block--${this.size}` : ''
        ]
      },
      moveDropdown() {
        return `moveBlock${this.index}Dropdown`
      },
      actionsDropdown() {
        return `action${this.block.id}Dropdown`
      },
      addDropdown() {
        return `add${this.block.id}Dropdown`
      },
      ...mapState({
        currentLocale: state => state.language.active,
        selectedMedias: state => state.mediaLibrary.selected,
        selectedBrowsers: state => state.browser.selected
      }),
      ...mapGetters(['fieldValueByName'])
    },
    watch: {
      opened() {
        this.visible = this.opened
      }
    },
    created() {
      if (this.block.ui && this.block.ui.isNew) {
        this.toggleExpand()
      }
    },
    methods: {
      normalizeTitleField(field) {
        if (!field) return { name: '', crop: null }

        if (typeof field === 'string') {
          return { name: field, crop: null }
        }

        if (Array.isArray(field)) {
          return {
            name: field[0] || '',
            crop:
              field[1] && typeof field[1] === 'object'
                ? field[1].crop || field[1].variant || null
                : field[1] || null
          }
        }

        if (field.name || field.field) {
          return {
            name: field.name || field.field,
            crop: field.crop || field.variant || null
          }
        }

        const entries = Object.entries(field)

        if (entries.length === 1) {
          return {
            name: entries[0][0],
            crop: entries[0][1] || null
          }
        }

        return { name: '', crop: null }
      },
      titleFieldValue(fieldName) {
        const textValue = this.blockFieldValue(fieldName)

        if (textValue !== null && textValue !== undefined && textValue !== '') {
          return textValue
        }

        return (
          this.selectedFieldValue(this.selectedMedias, fieldName) ||
          this.selectedFieldValue(this.selectedBrowsers, fieldName)
        )
      },
      selectedFieldValue(collection, fieldName) {
        if (!fieldName || !collection) return null

        const fieldPath = this.blockFieldName(fieldName)
        return collection[fieldPath] || null
      },
      pickFirstValue(source, keys, fallback = '') {
        return keys.reduce((value, key) => value || source[key], null) || fallback
      },
      formatTitleFieldValue(fieldValue, crop = null) {
        if (
          fieldValue === null ||
          fieldValue === undefined ||
          fieldValue === ''
        ) {
          return null
        }

        if (Array.isArray(fieldValue)) {
          return this.formatTitleFieldValue(fieldValue[0], crop)
        }

        if (typeof fieldValue === 'object') {
          const thumbnail = this.pickFirstValue(
            fieldValue,
            ['thumbnail', 'thumb', 'preview', 'url'],
            null
          )
          const textValue = this.pickFirstValue(
            fieldValue,
            ['name', 'title', 'alt', 'caption', 'label'],
            ''
          )

          if (fieldValue.endpointType) {
            return {
              type: 'browser',
              href: this.buildBrowserEditUrl(fieldValue),
              value: this.pickFirstValue(fieldValue, ['name', 'title'], ''),
              repository: this.browserRepositoryLabel(fieldValue.endpointType)
            }
          }

          if (crop && fieldValue.crops && fieldValue.crops[crop]) {
            return {
              type: 'media',
              thumbnail,
              alt: this.pickFirstValue(
                fieldValue,
                ['name', 'title', 'alt', 'caption'],
                crop
              )
            }
          }

          if (this.currentLocale && fieldValue[this.currentLocale.value]) {
            return {
              type: 'text',
              value: fieldValue[this.currentLocale.value]
            }
          }

          if (thumbnail) {
            return {
              type: 'media',
              thumbnail,
              alt: textValue
            }
          }

          return {
            type: 'text',
            value: textValue
          }
        }

        return {
          type: 'text',
          value: `${fieldValue}`
        }
      },
      toggleExpand() {
        this.visible = !this.visible
      },
      componentName(id) {
        return 'blocks[' + id + ']'
      },
      blockFieldName: function(fieldName) {
        if (!fieldName) return ''

        return `blocks[${this.block.id}][${fieldName}]`
      },
      blockFieldValue: function(fieldName) {
        if (!fieldName) return null

        const blockFieldName = this.blockFieldName(fieldName)
        return this.fieldValueByName(blockFieldName)
      },
      browserRepositoryLabel: function(endpointType) {
        if (!endpointType) return ''

        const className = `${endpointType}`
          .split('\\')
          .pop()
          .split('/')
          .pop()

        return className || ''
      },
      browserRepositorySlug: function(endpointType) {
        if (!endpointType) return ''

        const raw = `${endpointType}`
          .split('\\')
          .pop()
          .split('/')
          .pop()

        if (!raw) return ''

        const slug = raw
          .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
          .replace(/_/g, '-')
          .toLowerCase()

        if (slug.endsWith('s')) return slug
        if (slug.endsWith('y') && !/[aeiou]y$/.test(slug))
          return `${slug.slice(0, -1)}ies`

        return `${slug}s`
      },
      buildBrowserEditUrl: function(browserItem) {
        if (!browserItem) return null
        if (browserItem.edit) return browserItem.edit

        const repository = this.browserRepositorySlug(browserItem.endpointType)
        const id = browserItem.id

        if (!repository || id === null || id === undefined || id === '')
          return null

        return `/admin/${repository}/${encodeURIComponent(id)}/edit`
      }
    },
    beforeMount() {
      if (!this.$slots['dropdown-numbers']) this.withMoveDropdown = false
      if (!this.$slots['dropdown-add']) this.withAddDropdown = false
    }
  }
</script>

<style lang="scss" scoped>
  .block__content {
    display: none;
    padding: 25px 15px 15px 15px;
    background: $color__background;
  }

  .block--open {
    > .block__content {
      display: block;
    }

    > .block__header {
      border-bottom: 1px solid $color__border--light;
    }
  }

  .block__header {
    height: 50px;
    line-height: 50px;
    background: $color__block-bg;
    padding: 0 15px;
    position: relative;
    display: flex;
    background-clip: padding-box;
  }

  .block__right {
    display: flex;
    align-items: center;
    margin-left: auto;
  }

  .block__handle {
    position: relative;
    height: 10px;
    width: 40px;
    margin-left: 10px;
    flex-shrink: 0;
    @include dragGrid($color__drag, $color__block-bg);
  }

  .block__counter {
    border: 1px solid $color__border;
    border-radius: 50%;
    height: 26px;
    width: 26px;
    text-align: center;
    display: inline-block;
    line-height: 25px;
    margin-right: 10px;
    flex-shrink: 0;
    background: $color__background;
    color: $color__text--light;
    @include monospaced-figures('off'); // dont use monospaced figures here
    user-select: none;
    cursor: default;
    margin-top: calc((50px - 26px) / 2);
  }

  .dropdown .block__counter {
    cursor: pointer;

    &:hover {
      color: $color__text;
      border-color: $color__text;
    }
  }

  .dropdown--active .block__counter {
    color: $color__text;
    border-color: $color__text;
  }

  .block__title {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-overflow: ellipsis;
    font-weight: 600;
    overflow: hidden;
    white-space: nowrap;
    height: 50px;
    line-height: 50px;
    user-select: none;
  }

  .block__titleSeparator {
    flex-shrink: 0;
    padding-left: 2px;
    padding-right: 6px;
    color: $color__text--light;
  }

  .block__titleImage {
    width: 25px;
    height: 25px;
    border-radius: 2px;
    object-fit: cover;
    flex-shrink: 0;
  }

  .block__titleLink {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  .block__titleBrowserText {
    flex-shrink: 0;
  }

  .block__titlePart {
    display: flex;
    place-items: center;
  }

  .block__toggle {
    flex-grow: 1;
    display: flex;
    max-width: 50%;
    padding-right: 30px;

    .dropdown {
      display: inline-block;
      vertical-align: top;
    }

    .block__counter {
      vertical-align: top;
    }
  }

  .block__actions {
    text-align: right;
    display: flex;
    place-items: center;
    font-size: 0px;
    padding-top: calc((50px - 26px) / 2);
    padding-bottom: calc((50px - 26px) / 2);

    > * {
      margin-left: 10px;
      @include font-regular();
    }

    > button,
    .dropdown,
    .dropdown > button {
      display: inline-block;
      vertical-align: top;
      height: 26px;
    }
  }

  .block__actions {
    button[data-action] {
      visibility: hidden;
    }

    .dropdown--active button[data-action] {
      visibility: visible;
      display: inline-block;
    }
  }

  .block__header:hover {
    background: $color__block-bg--hover;

    .block__handle {
      &:before {
        background: dragGrid__bg($color__block-bg--hover);
      }
    }

    button[data-action] {
      visibility: visible;
      display: inline-block;
    }
  }

  .block__header:hover,
  .block--focus .block__header {
    button[data-action] {
      display: inline-block;
    }
  }

  /* Media field in block */
  .block__content {
    > .media,
    > .slideshow,
    > .browserField {
      margin: -15px;
      border: 0 none;
    }

    ::v-deep(.block__body) {
      > .media,
      > .slideshow,
      > .browserField {
        margin-left: -15px;
        margin-right: -15px;
        border: 0 none;
      }

      > .media:last-child,
      > .slideshow:last-child,
      > .browserField:last-child {
        margin-bottom: -15px;
      }
    }
  }

  // Small blocks (for repeater inside the block editor)
  .block--small {
    .block__header {
      background: $color__f--bg;

      .block__handle {
        background: dragGrid__dots($color__drag);

        &:before {
          background: dragGrid__bg($color__f--bg);
        }
      }
    }

    .block__header:hover {
      background: $color__light;

      .block__handle:before {
        background: dragGrid__bg($color__light);
      }
    }

    .block__counter {
      display: none;
    }
  }

</style>

<style lang="scss">
  .block {
    .block__content {
      .block__body {
        > .media,
        > .slideshow,
        > .browserField {
          margin-left: -15px;
          margin-right: -15px;
          border: 0 none;
        }

        > .media:last-child,
        > .slideshow:last-child,
        > .browserField:last-child {
          margin-bottom: -15px;
        }
      }
    }
  }
</style>

<template>
  <div class="editorIframe">
    <div class="editorIframe__empty" v-if="preview === ''">
      {{ title }}
    </div>
    <template v-else>
      <iframe
        v-if="sandbox"
        ref="frame"
        :key="frameKey"
        :srcdoc="preview"
        :sandbox="sandboxOptions"
        scrolling="no"
        @load="loadedPreview"
      />
    </template>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'

  export default {
    name: 'A17editorIframe',
    props: {
      block: {
        type: Object,
        default () { return {} }
      }
    },
    inject: ['sandbox'],
    computed: {
      preview() {
        // eslint-disable-next-line no-console
        console.log('preview', this.block.previewHtml, this.block.html)
        if (this.block && (this.block.previewHtml || this.block.html)) {
          return this.block.previewHtml || this.block.html
        }

        const fromStore = this.previewsById(this.block.id)
        if (!fromStore) return ''

        if (typeof fromStore === 'string') return fromStore
        if (fromStore && typeof fromStore === 'object' && typeof fromStore.html === 'string') {
          return fromStore.html
        }
        return ''
      },
      frameKey () {
        return `${this.block?.id || 'noid'}:${this.preview.length}`
      },
      title () {
        return this.block.title || ''
      },
      sandboxOptions () {
        return typeof this.sandbox === 'boolean'
          ? 'allow-same-origin allow-top-navigation allow-scripts'
          : this.sandbox.join(' ')
      },
      ...mapGetters(['previewsById'])
    },
    methods: {
      loadedPreview () {
        if (this.$refs.frame && this.$refs.frame.srcdoc) {
          this.$emit('loaded', this.$refs.frame)
          this.resize()
        }
      },
      resize () {
        if (!this.$refs.frame) return
        const doc = this.$refs.frame.contentWindow?.document
        if (!doc) return
        const frameBody = doc.body

        frameBody.style.overflow = 'hidden'

        const bodyStyle = window.getComputedStyle(frameBody)
        const mt = parseInt(bodyStyle.getPropertyValue('margin-top')) || 0
        const mb = parseInt(bodyStyle.getPropertyValue('margin-bottom')) || 0
        const frameHeight = frameBody.scrollHeight + mt + mb

        window.requestAnimationFrame(() => {
          this.$refs.frame.height = frameHeight + 'px'
        })
      }
    },
    mounted () {
      window.addEventListener('resize', this.resize)
    },
    beforeDestroy () {
      window.removeEventListener('resize', this.resize)
    }
  }
</script>

<style lang="scss" scoped>
  .editorIframe {
    cursor: pointer;
    overflow-y: hidden;
    padding: 5px;

    iframe {
      width: 100%;
      overflow: hidden;
      display: block;
    }
  }

  .editorIframe__empty {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    text-align: center;
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    color: rgba($color__text, 0.5);
    background-color: rgba($color_editor--active, 0.05);
    border: 1px solid rgba($color_editor--active, 0.33);
  }

  .editor__preview--dark .editorIframe__empty {
    color: rgba($color__background, 0.75);
    background-color: rgba($color_editor--active, 0.2);
    border: 1px solid rgba($color_editor--active, 0.5);
  }
</style>

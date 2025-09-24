<template>
  <a17-overlay
    ref="overlay"
    :title="$trans('editor.title')"
    :customClasses="htmlEditorClass"
    @close="close"
  >
    <template v-slot:overlay__header v-if="editorNames.length > 1">
      <a17-dropdown ref="editorDropdown" position="bottom-left" :maxWidth="400" :maxHeight="300">
            <a17-button class="editorDropdown__trigger" @click="$refs.editorDropdown.toggle()">
              {{ currentEditorLabel }} <span v-svg symbol="dropdown_module"></span>
            </a17-button>
            <template v-slot:dropdown__content>
              <div>
                <button type="button" class="editorDropdown" @click="updateEditorName(editorName.value)" v-for="editorName in editorNames" :key="editorName.value">
                  {{ editorName.label }}
                </button>
              </div>
            </template>
          </a17-dropdown>
    </template>
    <a17-blocks-list :editor-name="editorName" v-slot="{
      availableBlocks,
      hasBlockActive,
      savedBlocks,
      editorNames,
      moveBlock
    }">
      <div class="editor">
        <a17-button
          v-if="revisions.length"
          class="editor__leave"
          variant="editor"
          size="small"
          @click="openPreview"
        >
          <span class="hide--xsmall" v-svg symbol="preview"></span
          >{{ $trans('fields.block-editor.preview', 'Preview') }}
        </a17-button>

        <div class="editor__frame">
          <div class="editor__inner">
            <!-- SIDEBAR -->
            <div class="editor__sidebar" ref="sidebar">
              <div class="editorTabs">
                <button
                  type="button"
                  :class="[
                    'editorTabs__tab',
                    { 'is-active': activeTab === 'add' }
                  ]"
                  @click="activeTab = 'add'"
                >
                  {{ $trans('fields.block-editor.add-content', 'Add content') }}
                </button>
                <button
                  type="button"
                  :class="[
                    'editorTabs__tab',
                    { 'is-active': activeTab === 'reorder' }
                  ]"
                  @click="activeTab = 'reorder'"
                >
                  {{ $trans('fields.block-editor.reorder', 'Reorder') }}
                </button>
              </div>

              <!-- Tab panes -->
              <div v-show="activeTab === 'add'" class="editorPane">
                <a17-editorsidebar
                  :editor-name="editorName"
                  :hasBlockActive="hasBlockActive"
                  :editorNames="editorNames"
                  :blocks="availableBlocks"
                  @editorName:update="updateEditorName"
                >
                  {{ $trans('fields.block-editor.add-content', 'Add content') }}
                </a17-editorsidebar>
              </div>

              <div v-show="activeTab === 'reorder'" class="editorPane">
                <a17-blocks-reorder
                  :items="savedBlocks"
                  @reorder="payload => moveBlock(payload)"
                  :active-index="activeIndex"
                  @focus="handleFocusFromReorder"
                />
              </div>
            </div>

            <!-- RESIZER + PREVIEW are siblings of sidebar -->
            <div class="editor__resizer" @mousedown="resize"><span></span></div>

            <div class="editor__preview">
              <a17-editorpreview
                ref="previews"
                v-if="editorOpen"
                :editor-name="editorName"
                :blocks="savedBlocks"
                :hasBlockActive="hasBlockActive"
                :sandbox="previewSandbox"
                :bgColor="bgColor"
                @blocks:move="moveBlock"
                @visible:top="onTopVisible"
              />
            </div>
          </div>
        </div>
      </div>
    </a17-blocks-list>
  </a17-overlay>
</template>

<script>
  import { mapGetters,mapState } from 'vuex'

  import A17BlocksList from '@/components/blocks/BlocksList'
  import A17EditorPreview from '@/components/editor/EditorPreview.vue'
  import A17EditorSidebar from '@/components/editor/EditorSidebar.vue'
  import A17BlocksReorder from '@/components/editor/BlocksReorder.vue'
  import htmlClasses from '@/utils/htmlClasses'

  export default {
    name: 'A17Editor',
    components: {
      'a17-editorsidebar': A17EditorSidebar,
      'a17-editorpreview': A17EditorPreview,
      'a17-blocks-list': A17BlocksList,
      'a17-blocks-reorder': A17BlocksReorder
    },
    props: {
      bgColor: {
        type: String,
        default: '#FFFFFF'
      },
      previewSandbox: {
        type: [Boolean, Array],
        default: true
      }
    },
    data () {
      return {
        editorName: null,
        editorOpen: false,
        htmlEditorClass: htmlClasses.editor
      }
    },
    computed: {
      currentEditorLabel () {
        const current = this.editorNames && this.editorNames.find(editorName => editorName.value === this.editorName)
        return current && current.label
      },
      ...mapState({
        revisions: state => state.revision.all,
        editorNamesBase: state => state.blocks.editorNames
      }),
      ...mapGetters([
        'blocks'
      ]),
      editorNames() {
        return this.editorNamesBase.filter(editor => editor.nested === false)
      }
    },
    provide () {
      return {
        sandbox: this.previewSandbox
      }
    },
    methods: {
      onTopVisible({ index }) {
        this.activeIndex = index
      },
      handleFocusFromReorder({ id, index }) {
        if (this.$refs.previews && this.$refs.previews.scrollToBlock) {
          this.$refs.previews.scrollToBlock({ id, index })
        }
      },
      // EditorName functions
      initEditorName () {
        if (!this.editorName) {
          const editorName = (this.editorNames[0] && this.editorNames[0].value)
          this.updateEditorName(editorName)
        }
      },
      updateEditorName (editorName) {
        if (this.editorName !== editorName) {
          this.editorName = editorName
        }
      },
      // Editor state functions
      open (index, editorName = false) {
        if (editorName) {
          this.updateEditorName(editorName)
        }

        this.editorOpen = true

        this.$refs.overlay.open()
      },
      close () {
        this.editorOpen = false
      },
      resize () {
        window.addEventListener('mousemove', this.resizeSidebar, false)
        window.addEventListener('mouseup', this.stopResizeSidebar, false)
      },
      resizeSidebar (event) {
        const sidebar = this.$refs.sidebar
        const windowWidth = window.innerWidth
        if (sidebar) {
          sidebar.style.width =
            ((event.clientX - sidebar.offsetLeft) / windowWidth) * 100 + '%'
        }
      },
      stopResizeSidebar () {
        window.removeEventListener('mousemove', this.resizeSidebar, false)
        window.removeEventListener('mouseup', this.stopResizeSidebar, false)

        // resize all previews
        this.$refs.previews.resizeAllIframes()
      },
      // Open Revision modal
      openPreview () {
        if (this.$root.$refs.preview) this.$root.$refs.preview.open()
      }
    },
    created () {
      this.initEditorName()
    }
  }
</script>

<style lang="scss" scoped>
  $height__nav: 80px;

  /* Tabs */
  .editorTabs {
    display: flex;
    background: $color__border--light;
    border-bottom: 1px solid $color__border;
    flex: 0 0 auto; /* keep height natural */
  }
  .editorTabs__tab {
    flex: 1 1 50%;
    padding: 10px 12px;
    text-align: center;
    font-weight: 600;
    cursor: pointer;
    background: transparent;
    border: 0;
    &.is-active {
      background: $color__background;
      border-bottom: 2px solid $color__drag;
    }
  }
  .editorPane {
    padding: 8px 0;
    flex: 1 1 auto;
    overflow: auto;
  }

  .editor {
    display: block;
    width: 100%;
    padding: 0;
    position: relative;
    flex-grow: 1;
    background-color: $color__background;
  }

  .editor__leave {
    position: fixed;
    right: 20px;
    top: 13px;
    z-index: $zindex__overlay + 1;
  }

  .editor__frame {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-flow: column nowrap;
  }

  .editor__inner {
    position: relative;
    width: 100%;
    overflow: hidden;
    flex-grow: 1;
    display: flex;
    flex-flow: row nowrap;
    // height: calc(100vh - 60px);
  }

  /* Sidebar / Preview / Resizer */
  .editor__sidebar {
    background: $color__border--light;
    width: 30vw;
    min-width: 400px;
    display: flex; /* stack tabs + pane vertically */
    flex-direction: column; /* tabs on top, pane below */
  }

  .editor__resizer {
    width: 10px;
    min-width: 10px;
    cursor: col-resize;
    background: $color__border--light;
    display: flex;
    align-items: center;
    justify-content: space-between;
    user-select: none;

    span {
      width: 2px;
      height: 20px;
      display: block;
      background: dragGrid__dots($color__drag);
      overflow: hidden;
      margin-left: auto;
      margin-right: auto;
    }
  }

  .editor__preview {
    flex-grow: 1;
    position: relative;
    min-width: 300px;
    color: $color__text--light;
  }

  .editor__preview--dark {
    color: $color__background;
  }

  .editorDropdown__trigger {
    color: inherit;
  }
</style>

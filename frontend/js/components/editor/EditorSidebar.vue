<template>
  <div class="editorSidebar">
    <input
      type="hidden"
      :name="`blocks_layout[${editorName}]`"
      ref="layoutInput"
    />
    <div v-show="hasBlockActive">
      <a17-blocks-list :editor-name="editorName" v-slot="{ allSavedBlocks }">
        <div class="editorSidebar__edit-list">
          <a17-blockeditor-model
            :block="savedBlock"
            :editor-name="editorName"
            v-for="savedBlock in allSavedBlocks"
            :key="savedBlock.id"
            v-slot="{ block, isActive, blockIndex, move, remove, unEdit }"
          >
            <div class="editorSidebar__edit-block">
              <a17-sidebar-block-item
                :block="block"
                v-show="isActive"
                :blockIndex="blockIndex"
                :blocksLength="allSavedBlocks.length"
                @block:move="move"
                @block:delete="deleteBlock(remove)"
              />
              <div class="editorSidebar__actions">
                <a17-button
                  variant="action"
                  @click="saveBlock(unEdit, blockIndex)"
                >
                  {{ $trans('editor.done') }}
                </a17-button>
                <a17-button
                  variant="secondary"
                  @click="cancelBlock(unEdit, blockIndex)"
                >
                  {{ $trans('editor.cancel') }}
                </a17-button>
              </div>
            </div>
          </a17-blockeditor-model>
        </div>
      </a17-blocks-list>
    </div>

    <template v-if="!hasBlockActive">
      <div class="editorSidebar__list">
        <a17-sidebar-block-list :blocks="blocks" />
      </div>

      <div class="editorSidebar__actions">
        <a17-button
          v-if="isSubmitDisabled(submitOptions[0])"
          variant="validate"
          :disabled="true"
        >{{ submitOptions[0].text }}</a17-button
        >
        <a17-button
          v-else
          @click="saveForm(submitOptions[0].name)"
          :name="submitOptions[0].name"
          variant="validate"
        >{{ submitOptions[0].text }}</a17-button
        >
      </div>
    </template>
  </div>
</template>

<script>
  import A17BlockEditorModel from '@/components/blocks/BlockEditorModel'
  import A17BlocksList from '@/components/blocks/BlocksList'
  import A17EditorSidebarBlockItem from '@/components/editor/EditorSidebarBlockItem'
  import A17EditorSidebarBlockList from '@/components/editor/EditorSidebarBlockList'
  import { BlockEditorMixin } from '@/mixins'
  import { PUBLICATION, BLOCKS } from '@/store/mutations'

  export default {
    name: 'A17editorSidebar',
    props: {
      hasBlockActive: { type: Boolean, default: false },
      activeBlock: { type: Object, default: () => {} },
      editorName: { type: String, required: true },
      editorNames: { type: Array, default: () => [] }
    },
    components: {
      'a17-sidebar-block-item': A17EditorSidebarBlockItem,
      'a17-sidebar-block-list': A17EditorSidebarBlockList,
      'a17-blocks-list': A17BlocksList,
      'a17-blockeditor-model': A17BlockEditorModel
    },
    mixins: [BlockEditorMixin],
    computed: {
      submitOptions() {
        return this.$store.getters.getSubmitOptions
      }
    },
    methods: {
      isSubmitDisabled(btn) {
        return btn.hasOwnProperty('disabled') ? btn.disabled === true : false
      },

      buildLayoutArray() {
        const blocks = this.$store.getters.blocks(this.editorName) || []
        return blocks.map(b => ({
          id: b.id,
          grid: {
            x: b.grid && Number.isFinite(b.grid.x) ? b.grid.x : 0,
            y: b.grid && Number.isFinite(b.grid.y) ? b.grid.y : 0,
            w: b.grid && Number.isFinite(b.grid.w) ? b.grid.w : 12,
            h: b.grid && Number.isFinite(b.grid.h) ? b.grid.h : 3
          }
        }))
      },

      injectGridFieldsIntoFormState() {
        const formFields = (this.$store.state.form && this.$store.state.form.fields) || []
        const layout = this.buildLayoutArray()

        const ids = new Set(layout.map(l => String(l.id)))
        const isGridFieldForCurrent = f => {
          if (!f || !f.name) return false
          const m = f.name.match(/^blocks\[(\d+)\]\[grid\]$/)
          return m && ids.has(m[1])
        }
        const filtered = formFields.filter(f => !isGridFieldForCurrent(f))

        const gridFields = layout.map(l => ({
          name: `blocks[${l.id}][grid]`,
          value: l.grid
        }))

        const nextFields = filtered.concat(gridFields)

        // Safely replace form.fields (avoid Vuex strict warnings if enabled)
        if (typeof this.$store._withCommit === 'function') {
          this.$store._withCommit(() => {
            this.$store.state.form.fields = nextFields
          })
        } else {
          // fallback (most setups won't be strict in prod)
          this.$store.state.form.fields = nextFields
        }

        const currentBlocks = this.$store.getters.blocks(this.editorName) || []
        const byId = new Map(layout.map(l => [String(l.id), l.grid]))
        const updatedBlocks = currentBlocks.map(b => {
          const g = byId.get(String(b.id))
          return g ? { ...b, content: { ...(b.content || {}), grid: g } } : b
        })
        this.$store.commit(BLOCKS.REORDER_BLOCKS, {
          editorName: this.editorName,
          value: updatedBlocks
        })
      },

      setDebugHiddenField() {
        if (!this.$refs.layoutInput) return
        this.$refs.layoutInput.value = JSON.stringify(this.buildLayoutArray())
      },

      async saveForm(buttonName) {
        // 1) Put grid into Vuex form fields so getFormData() will include it in each block.content
        this.injectGridFieldsIntoFormState()

        // 2) Hidden field for inspecting
        this.setDebugHiddenField()

        // 3) Proceed with the normal Twill save
        this.$store.commit(PUBLICATION.UPDATE_SAVE_TYPE, buttonName)
        if (this.$root.submitForm) this.$root.submitForm()
      }
    }
  }
</script>

<style lang="scss" scoped>
  .editorSidebar {
    margin: 20px 0 20px 0;
    position: relative;
    overflow: hidden;
    height: calc(100% - 40px);
  }

  .editorSidebar__list {
    overflow-y: auto;
    padding: 0 10px 0 20px;
    position: absolute;
    top: 0;
    bottom: 60px;
    left: 0;
    right: 0;
  }

  .editorSidebar__actions {
    position: absolute;
    width: 100%;
    left: 0;
    bottom: 0;
    padding: 20px 10px 0 20px;
    background: $color__border--light;
    display: flex;

    button {
      width: calc(50% - 10px);
    }

    button + button {
      margin-left: 20px;
    }

    button.button--validate:last-child {
      width: 100%;
      margin-left: 0;
    }
  }
</style>

<style lang="scss">
  .editorSidebar__body {
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
</style>

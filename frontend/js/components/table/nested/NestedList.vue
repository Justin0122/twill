<template>
  <draggable
    class="nested__dropArea"
    v-bind="draggableOptions"
    :class="nestedDropAreaClasses"
    v-model="rows"
    :tag="'ul'"
  >
    <li
      class="nested-datatable__item"
      v-for="(row, index) in rows"
      :class="[
        haveChildren(row.children),
        { 'nested-datatable__item--has-children': row.children && row.children.length }
      ]"
      :key="depth + '-' + row.id"
    >
      <div
        class="nested-item-header"
        @click="handleRowClick(row)"
        :class="{ 'has-children': row.children && row.children.length }"
      >
        <button
          v-if="row.children && row.children.length"
          class="collapse-toggle"
          :class="{ 'is-collapsed': isCollapsed(row) }"
          :aria-expanded="!isCollapsed(row)"
          aria-label="Toggle children"
          @click.stop="toggleCollapse(row)"
        >
          <span class="collapse-icon" aria-hidden="true">
            <svg viewBox="0 0 20 20" focusable="false">
              <path
                d="M7.5 4.5 L13 10 L7.5 15.5"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </span>
        </button>

        <a17-nested-item
          :index="index"
          :row="row"
          :columns="columns"
          @click.stop
        />
      </div>

      <a17-nested-list
        v-if="row.children && depth < maxDepth && !isCollapsed(row)"
        :maxDepth="maxDepth"
        :depth="depth + 1"
        :parentId="row.id"
        :items="row.children"
        :nested="true"
        :draggable="true"
      />
    </li>
  </draggable>
</template>

<script>
  import { VueDraggableNext } from 'vue-draggable-next'
  import { DatatableMixin, DraggableMixin, NestedDraggableMixin } from '@/mixins/index'
  import { DATATABLE } from '@/store/mutations'
  import NestedItem from './NestedItem'

  export default {
    name: 'a17-nested-list',
    components: {
      'a17-nested-item': NestedItem,
      draggable: VueDraggableNext
    },
    mixins: [DatatableMixin, DraggableMixin, NestedDraggableMixin],
    props: {
      index: {
        type: Number,
        default: 0
      },
      items: {
        type: Array,
        default: () => []
      }
    },
    data() {
      return {
        handle: '.tablecell__handle',
        collapsedRows: {}
      }
    },
    computed: {
      styleDepth() {
        return {
          marginLeft: this.depth === 0 ? '0px' : '60px'
        }
      },
      rows: {
        get() {
          return this.parentId > -1 ? this.items : this.$store.state.datatable.data
        },
        set(value) {
          const data = {
            parentId: this.parentId,
            val: value
          }
          const isChangingParents = this.rows.length !== data.val.length
          if (this.parentId > -1) {
            this.$store.commit(DATATABLE.UPDATE_DATATABLE_NESTED, data)
          } else {
            this.$store.commit(DATATABLE.UPDATE_DATATABLE_DATA, value)
          }
          this.saveNewTree(isChangingParents)
        }
      },
      nestedDropAreaClasses() {
        return [
          this.rows.length === 0 ? 'nested__dropArea--empty' : '',
          this.depth ? `nested__dropArea--depth nested__dropArea--depth${Math.min(10, this.depth)}` : ''
        ]
      },
      draggableOptions() {
        return {
          ...this.dragOptions,
          fallbackTolerance: 5,
          forceFallback: true,
          fallbackOnBody: true,
          scrollSensitivity: 60,
          scrollSpeed: 20,

          group: {
            name: this.name
          }
        }
      }
    },
    methods: {
      haveChildren(children) {
        return {
          'nested-datatable__item--empty': (children || []).length === 0 && this.depth < this.maxDepth
        }
      },
      toggleCollapse(row) {
        this.collapsedRows[row.id] = !this.isCollapsed(row)
      },
      isCollapsed(row) {
        return !!this.collapsedRows[row.id]
      },
      handleRowClick(row) {
        if (row.children && row.children.length) {
          this.toggleCollapse(row)
        }
      }
    }
  }
</script>

<style lang="scss" scoped>
  .nested-datatable__item {
    border: 1px solid #F2F2F2;
    margin-top: -1px;

    .nested-datatable__item {
      border-right: 0 none;
    }

    &.sortable-ghost {
      opacity: 1 !important;
      background-color: $color__f--bg;
    }

    &.sortable-chosen {
      opacity: 0.5;
    }

    &.sortable-drag {
      display: block;
    }
  }

  .nested__dropArea {
    padding: 15px 0px 15px 0px;

    * {
      will-change: auto;
    }

    .nested__dropArea {
      padding-left: 15px;
    }

    &.nested__dropArea--empty {
      padding-top: 20px;
      min-height: 20px;
      margin-top: -20px;
    }
  }

  .nested-item:hover + .nested__dropArea {
    background: $color__f--bg;

    .nested-datatable__item {
      background: white;
    }
  }

  .nested__dropArea--depth > li > div {
    &::after {
      content: '';
      display: block;
      height: 6px;
      border-left: 1px solid #D9D9D9;
      border-bottom: 1px solid #D9D9D9;
      position: absolute;
      top: calc(50% - 3px);
      left: 20px;
      background-color: transparent;
      width: 0;
      pointer-events: none;
    }
  }

  .nested__dropArea--depth1 > li > div {
    padding-left: 50px;

    &::after {
      width: 30px;
    }
  }

  @for $i from 2 through 10 {
    .nested__dropArea--depth#{$i} > li > div {
      padding-left: #{$i * 35px};

      &::after {
        width: #{($i * 35px) - 20px};
      }
    }
  }
</style>

<style lang="scss">
  .nested__dropArea {
    &.nested__dropArea--empty {
      .nested-item {
        margin-bottom: 0;
      }
    }

    &.nested-datatable__item--empty {
      > .nested-item {
        margin-bottom: 0;
      }
    }
  }

  .nested-item-header {
    cursor: default;
    position: relative;

    &.has-children {
      cursor: pointer;

      &:hover {
        background: rgba(0, 0, 0, 0.02);
      }
    }
  }

  .nested__dropArea--depth > li.nested-datatable__item--has-children {
    position: relative;
  }

  .nested__dropArea--depth > li.nested-datatable__item--has-children::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;

    left: 20px;

    width: 2px;
    border-radius: 2px;
    background: rgba(0, 0, 0, 0.12);
    pointer-events: none;
  }

  .collapse-toggle {
    background-color: #eb8004;
    position: absolute;
    left: -28px;
    top: 0;
    bottom: 0;
    transition: background-color 200ms ease-in-out;

    width: 28px;
    display: flex;
    align-items: center;
    justify-content: center;

    padding: 0;
    border: none;
    cursor: pointer;

    &:focus-visible {
      outline: 2px solid rgba(0, 0, 0, 0.25);
      outline-offset: 2px;
      border-radius: 6px;
    }
  }

  .collapse-toggle.is-collapsed {
    background-color: #009fda;
  }

  .collapse-icon {
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    color: white;
    border-radius: 8px;

    transition: transform 120ms ease, color 120ms ease, background 120ms ease;
  }

  .collapse-toggle:not(.is-collapsed) .collapse-icon {
    transform: rotate(90deg);
  }

  .nested-item-header.has-children:hover .collapse-icon {
    color: rgba(0, 0, 0, 0.75);
    background: rgba(0, 0, 0, 0.04);
  }

  .nested__dropArea--depth > li.nested-datatable__item--has-children:hover::before {
    background: rgba(0, 0, 0, 0.18);
  }

  .nested-item {
    position: relative;
  }

  button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1em;
    padding: 0 4px;
  }
</style>

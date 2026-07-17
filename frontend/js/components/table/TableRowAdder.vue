<template>
  <div class="tableRowAdder" v-if="creatable">
    <button type="button"
            class="tableRowAdder__button"
            :aria-label="$trans('listing.add-new-button', 'Add new')"
            @click.prevent.stop="add">
      <span v-svg symbol="add"></span>
    </button>
  </div>
</template>

<script>
  import { DATATABLE } from '@/store/mutations'

  export default {
    name: 'A17TableRowAdder',
    props: {
      index: {
        type: Number,
        required: true
      },
      parentId: {
        type: Number,
        default: -1
      },
      nested: {
        type: Boolean,
        default: false
      }
    },
    data: function () {
      return {
        creatable: false
      }
    },
    mounted: function () {
      this.creatable = typeof this.$root.create === 'function' && !!this.$root.$refs.editionModal
    },
    methods: {
      add: function () {
        this.$store.commit(DATATABLE.UPDATE_DATATABLE_INSERT_CONTEXT, {
          index: this.index,
          parentId: this.parentId,
          nested: this.nested
        })

        this.$root.create()
      }
    }
  }
</script>

<style lang="scss" scoped>

  .tableRowAdder {
    position: absolute;
    top: -10px;
    left: 0;
    right: 0;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    opacity: 0;

    &:hover,
    &:focus-within {
      opacity: 1;
    }

    &.tableRowAdder--standalone {
      position: relative;
      top: 0;
    }

    &::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      border-top: 1px solid $color__action;
      pointer-events: none;
    }
  }

  .tableRowAdder__button {
    @include btn-reset;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background-color: $color__action;
    color: $color__background;

    &:hover,
    &:focus {
      background-color: $color__action--hover;
    }

    .icon {
      display: block;
      fill: currentColor;
    }
  }
</style>

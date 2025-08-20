import { mapState } from 'vuex'

export default {
  props: {
    items: {
      type: Array,
      default: () => []
    },
    selectedItems: {
      type: Array,
      default: () => []
    },
    usedItems: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    ...mapState({
      itemsLoading: state => state.mediaLibrary.loading
    }),
    replacingMediaIds: function() {
      return this.itemsLoading.reduce((agg, curr) => {
        if (curr.isReplacement) {
          agg[curr.replacementId] = curr.id
        }
        return agg
      }, {})
    },
    selectedIdsSet() {
      return new Set((this.selectedItems || []).map(i => i.id))
    },
    usedIdsSet() {
      return new Set((this.usedItems || []).map(i => i.id))
    }

  },
  methods: {
    isSelected: function(item, keys = ['id']) {
      if (keys.length === 1 && keys[0] === 'id') {
        return this.selectedIdsSet.has(item.id)
      }
      return Boolean(
        this.selectedItems.find(sItem =>
          keys.every(key => sItem[key] === item[key])
        )
      )
    },

    },
    isUsed: function(item, keys = ['id']) {
      if (keys.length === 1 && keys[0] === 'id') {
        return this.usedIdsSet.has(item.id)
      }

      return Boolean(
        this.usedItems.find(uItem =>
          keys.every(key => uItem[key] === item[key])
        )
      )
    },
    toggleSelection: function(item) {
      this.$emit('change', item)
    },
    shiftToggleSelection: function(item) {
      this.$emit('shiftChange', item, true)
    }
  }
}

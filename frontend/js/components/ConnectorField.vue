<template>
  <div>
    <template v-if="!keepAlive">
      <div v-if="open" ref="fieldContainer">
        <slot></slot>
      </div>
    </template>
    <template v-else>
      <div v-show="open">
        <slot></slot>
      </div>
    </template>
  </div>
</template>

<script>
  import clone from 'lodash/clone'
  import isEqual from 'lodash/isEqual'
  import { mapGetters, mapState } from 'vuex'

  export default {
    name: 'A17ConnectorField',
    props: {
      fieldName: { type: String, required: true },
      requiredFieldValues: { default: '' },
      inModal: { type: Boolean, default: false },
      keepAlive: { type: Boolean, default: false },
      arrayContains: { type: Boolean, default: true },
      isValueEqual: { type: Boolean, default: true },
      isBrowser: { type: Boolean, default: false },
      matchEmptyBrowser: { type: Boolean, default: false }
    },
    computed: {
      storedValue() {
        if (this.inModal) return this.modalFieldValueByName(this.fieldName)
        if (this.isBrowser) return this.selectedBrowser[this.fieldName]
        return this.fieldValueByName(this.fieldName)
      },
      ...mapGetters(['fieldValueByName', 'modalFieldValueByName']),
      ...mapState({
        fields: state => state.form.fields,
        modalFields: state => state.form.modalFields,
        selectedBrowser: state => state.browser.selected
      })
    },
    data() {
      return { open: false }
    },
    watch: {
      storedValue(fieldInstore) {
        this.toggleVisibility(fieldInstore)
      }
    },
    methods: {
      // --- deep destroy across slotted children ---
      destroyChildValues() {
        if (!this.$refs.fieldContainer) return
        if (typeof this.$slots.default !== 'function') return
        const vnodes = this.$slots.default()
        this._destroyValuesInVNodes(vnodes)
      },
      _destroyValuesInVNodes(vnodes) {
        if (!vnodes) return
        const list = Array.isArray(vnodes) ? vnodes : [vnodes]

        for (const vnode of list) {
          // Component instance proxy (public)
          const comp = vnode && vnode.component && vnode.component.proxy

          // 1) Direct method on the component
          if (comp && typeof comp.destroyValue === 'function') {
            try { comp.destroyValue() } catch (_) {}
          }

          // 2) Child's $refs.field -> may be array or single
          if (comp && comp.$refs && comp.$refs.field) {
            const fieldRef = Array.isArray(comp.$refs.field)
              ? comp.$refs.field[0]
              : comp.$refs.field
            if (fieldRef && typeof fieldRef.destroyValue === 'function') {
              try { fieldRef.destroyValue() } catch (_) {}
            }
          }

          // 3) Recurse into the child's default slot, if any
          if (comp && comp.$slots && typeof comp.$slots.default === 'function') {
            try { this._destroyValuesInVNodes(comp.$slots.default()) } catch (_) {}
          }

          // 4) Recurse into vnode children (fragments/elements)
          if (vnode && Array.isArray(vnode.children)) {
            this._destroyValuesInVNodes(vnode.children)
          }
        }
      },

      // --- compute next state first, then clear if hiding ---
      toggleVisibility(value) {
        // Compute next 'open' without mutating yet
        let nextOpen = false

        if (this.isBrowser) {
          const browserLength = (value && value.length) ?? 0
          if (this.matchEmptyBrowser && browserLength === 0) {
            nextOpen = true
          } else {
            nextOpen = this.matchEmptyBrowser ? false : browserLength > 0
          }
        } else {
          const newValue = clone(value)
          const newFieldValues = clone(this.requiredFieldValues)
          const newFieldValuesArray = Array.isArray(newFieldValues)
            ? newFieldValues
            : [newFieldValues]

          if (Array.isArray(newFieldValues)) newFieldValues.sort()
          if (Array.isArray(newValue)) newValue.sort()

          if (this.isValueEqual) {
            if (Array.isArray(newValue)) {
              nextOpen = this.arrayContains
                ? newFieldValuesArray.some(v => newValue.includes(v))
                : JSON.stringify(newFieldValuesArray) === JSON.stringify(newValue)
            } else {
              nextOpen = Array.isArray(newFieldValues)
                ? newFieldValues.indexOf(newValue) !== -1
                : isEqual(newValue, newFieldValues)
            }
          } else {
            if (Array.isArray(newValue)) {
              nextOpen = this.arrayContains
                ? newFieldValuesArray.every(v => !newValue.includes(v))
                : JSON.stringify(newFieldValuesArray) !== JSON.stringify(newValue)
            } else {
              nextOpen = Array.isArray(newFieldValues)
                ? newFieldValues.indexOf(newValue) === -1
                : !isEqual(newValue, newFieldValues)
            }
          }
        }

        // If we are about to hide, clear nested field values safely
        if (this.open && !nextOpen) {
          this.destroyChildValues()
        }

        this.open = nextOpen
      }
    },
    mounted() {
      this.$nextTick(() => {
        this.toggleVisibility(this.storedValue)
      })
    }
  }
</script>

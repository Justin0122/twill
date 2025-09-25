import isEqual from 'lodash/isEqual'
import { mapGetters, mapState } from 'vuex'

import { FORM } from '@/store/mutations'

export default {
  props: {
    hasDefaultStore: {
      type: Boolean,
      default: false
    },
    inModal: {
      type: Boolean,
      default: false
    },
    inStore: {
      type: String,
      default: ''
    },
    fieldName: {
      type: String,
      default: ''
    }
  },
  computed: {
    storedValue() {
      return this.inModal
        ? this.modalFieldValueByName(this.getFieldName())
        : this.fieldValueByName(this.getFieldName())
    },
    ...mapGetters(['fieldValueByName', 'modalFieldValueByName']),
    ...mapState({
      submitting: state => state.form.loading,
      fields: state => state.form.fields, // Fields in the form
      modalFields: state => state.form.modalFields // Fields in the create/edit modal
    })
  },
  watch: {
    storedValue(fieldInstore) {
      if (this.inStore === '') return

      const currentValue = this[this.inStore]
      const newValue = this.locale
        ? (fieldInstore && typeof fieldInstore === 'object'
          ? fieldInstore?.[this.locale.value] ?? null
          : null)
        : (fieldInstore ?? null)

      // If different, update the UI (prefer component hook if present)
      if (!isEqual(currentValue, newValue)) {
        if (typeof this.updateFromStore === 'function') {
          this.updateFromStore(newValue)
        } else {
          this[this.inStore] = newValue
        }
      }
    }
  },
  methods: {
    getFieldName() {
      return this.fieldName !== '' ? this.fieldName : this.name
    },
    // Save the value into the store
    saveIntoStore(value) {
      if (this.inStore === '') return

      const newValue = value !== undefined ? value : this[this.inStore]

      // Build payload
      const field = {
        name: this.getFieldName(),
        value: newValue
      }
      if (this.locale && this.locale.value) field.locale = this.locale.value

      // In Modal or in Form
      if (this.inModal) this.$store.commit(FORM.UPDATE_MODAL_FIELD, field)
      else this.$store.commit(FORM.UPDATE_FORM_FIELD, field)
    },
    preventSubmit() {
      this.$store.commit(FORM.PREVENT_SUBMIT)
    },
    allowSubmit() {
      this.$store.commit(FORM.ALLOW_SUBMIT)
    },
    destroyValue() {
      if (this.inStore !== '') {
        // Delete form field from store because the field has been removed
        if (this.inModal)
          this.$store.commit(FORM.REMOVE_MODAL_FIELD, this.getFieldName())
        else this.$store.commit(FORM.REMOVE_FORM_FIELD, this.getFieldName())
      }
    }
  },
  // Vue 3 Options API keeps beforeMount; just add null-safe guards
  beforeMount() {
    const fieldName = this.getFieldName()
    if (this.inStore === '') return
    if (fieldName === '') return

    const fields = this.inModal ? this.modalFields : this.fields

    const fieldInStore = fields.filter(field => field.name === fieldName)

    if (fieldInStore.length) {
      // Init value with the one from the store
      const storeVal = fieldInStore[0].value

      if (this.locale && this.locale.value) {
        this[this.inStore] =
          (storeVal && typeof storeVal === 'object'
            ? storeVal?.[this.locale.value] ?? null
            : null)
      } else {
        this[this.inStore] = storeVal ?? null
      }
    } else if (this.hasDefaultStore) {
      // Init value with the one present in the component itself
      this.saveIntoStore()
    }
  }
}

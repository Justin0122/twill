export default {
  namespaced: true,
  state: () => ({ byId: {} }),
  getters: {
    byBlockId: (s) => (id) => s.byId[id] || null
  },
  mutations: {
    set(state, { id, payload }) {
      const safe = JSON.parse(JSON.stringify(payload ?? {}))
      state.byId = { ...state.byId, [id]: safe }
    },
    clear(state, id) {
      const { [id]: _drop, ...rest } = state.byId
      state.byId = rest
    }
  }
}

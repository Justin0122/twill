import axios from 'axios'

import { globalError } from '@/utils/errors'

const component = 'MEDIA-LIBRARY'

export default {
  get(endpoint, params, callback, errorCallback) {
    // Params: query, page, type, folder, etc.
    axios.get(endpoint, { params }).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library get error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  update(endpoint, params, callback, errorCallback) {
    axios.put(endpoint, params).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library update error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  delete(endpoint, callback, errorCallback) {
    axios.delete(endpoint).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library delete error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  bulkDelete(endpoint, params, callback, errorCallback) {
    axios.put(endpoint, params).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library bulk delete error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  // -----------------------------
  // Folder support
  // -----------------------------

  // Fetch nested folder tree for the given media type
  // GET {endpoint}/folders?type=image
  getFolders(endpoint, params, callback, errorCallback) {
    axios.get(`${endpoint}/folders`, { params }).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library folders get error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  renameFolder (endpoint, id, payload, onSuccess, onError) {
    axios.patch(`${endpoint}/folders/${id}`, payload, { headers: { Accept: 'application/json' } })
      .then(resp => onSuccess && onSuccess(resp))
      .catch(err => onError && onError(err.response || err))
  },

  deleteFolder(endpoint, id, callback, errorCallback) {
    axios
      .delete(`${endpoint}/folders/${id}`, { headers: { Accept: 'application/json' } })
      .then(resp => callback && callback(resp))
      .catch(err => errorCallback && errorCallback(err.response || err))
  },

  createFolder(endpoint, body, callback, errorCallback) {
    axios.post(`${endpoint}/folders`, body).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library create folder error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },

  // Move media items to a target folder
  // POST {endpoint}/folders/move  body: { type, target: 'tuwi/sub', mediaIds: [1,2,3] }
  moveToFolder(endpoint, body, callback, errorCallback) {
    axios.post(`${endpoint}/folders/move`, body).then(
      function(resp) {
        if (callback && typeof callback === 'function') callback(resp)
      },
      function(resp) {
        const error = {
          message: 'Media library move to folder error.',
          value: resp
        }
        globalError(component, error)
        if (errorCallback && typeof errorCallback === 'function')
          errorCallback(resp)
      }
    )
  },
}

import { configureStore } from '@reduxjs/toolkit'
import preferencesReducer from './slices/preferencesSlice.js'

export const store = configureStore({
  reducer: {
    preferences: preferencesReducer,
  },
})

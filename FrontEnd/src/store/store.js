import { configureStore } from '@reduxjs/toolkit'
import meteoReducer from './slices/meteoSlice.js'
import preferencesReducer from './slices/preferencesSlice.js'

export const store = configureStore({
  reducer: {
    meteo: meteoReducer,
    preferences: preferencesReducer,
  },
})

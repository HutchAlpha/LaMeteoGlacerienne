import { createAsyncThunk, createSlice } from '@reduxjs/toolkit'

export const chargerDonneesMeteo = createAsyncThunk(
  'meteo/chargerDonnees',
  async (ville, { rejectWithValue }) => {
    const query = new URLSearchParams({ ville })
    const response = await fetch(`/api/meteo?${query}`)
    const payload = await response.json()

    if (!response.ok || payload.erreur) {
      return rejectWithValue(payload.erreur || 'Impossible de charger les données météo.')
    }

    if (!Array.isArray(payload.donnees)) {
      return rejectWithValue('La réponse de l’API météo est invalide.')
    }

    return payload
  },
  {
    condition: (_, { getState }) => getState().meteo.status !== 'loading',
  },
)

export const chargerHistoriqueMeteo = createAsyncThunk(
  'meteo/chargerHistorique',
  async (_, { rejectWithValue }) => {
    const response = await fetch('/api/historique')
    const payload = await response.json()

    if (!response.ok || !Array.isArray(payload.donnees)) {
      return rejectWithValue(payload.erreur || 'Impossible de charger l’historique météo.')
    }

    return payload
  },
  {
    condition: (_, { getState }) => getState().meteo.historiqueStatus !== 'loading',
  },
)

const meteoSlice = createSlice({
  name: 'meteo',
  initialState: {
    ville: '',
    donnees: [],
    status: 'idle',
    erreur: null,
    historique: [],
    historiqueTotal: 0,
    historiqueStatus: 'idle',
    historiqueErreur: null,
  },
  reducers: {},
  extraReducers(builder) {
    builder
      .addCase(chargerDonneesMeteo.pending, (state) => {
        state.status = 'loading'
        state.erreur = null
      })
      .addCase(chargerDonneesMeteo.fulfilled, (state, action) => {
        state.status = 'succeeded'
        state.ville = action.payload.ville
        state.donnees = action.payload.donnees
      })
      .addCase(chargerDonneesMeteo.rejected, (state, action) => {
        state.status = 'failed'
        state.erreur = action.payload || action.error.message
      })
      .addCase(chargerHistoriqueMeteo.pending, (state) => {
        state.historiqueStatus = 'loading'
        state.historiqueErreur = null
      })
      .addCase(chargerHistoriqueMeteo.fulfilled, (state, action) => {
        state.historiqueStatus = 'succeeded'
        state.historique = action.payload.donnees
        state.historiqueTotal = action.payload.total
      })
      .addCase(chargerHistoriqueMeteo.rejected, (state, action) => {
        state.historiqueStatus = 'failed'
        state.historiqueErreur = action.payload || action.error.message
      })
  },
})

export default meteoSlice.reducer

import express from 'express'

const app = express()
const port = Number(process.env.PORT) || 3000

app.use(express.json())

app.get('/api/health', (_request, response) => {
  response.json({ status: 'ok', service: 'la-meteo-glacerienne-api' })
})

app.listen(port, () => {
  console.log(`API disponible sur http://localhost:${port}`)
})

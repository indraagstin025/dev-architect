import express from 'express';

const app = express();
app.use(express.json());

app.get('/', (_req, res) => {
  res.json({ status: 'ok', app: '__SLUG__' });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`__SLUG__ listening on http://localhost:${PORT}`);
});

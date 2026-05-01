
import fetch from 'node-fetch';

const API_KEY = 'AIzaSyD15m2l7F5njC5RxMqvM2P9ENoF5o-V2Qk';

async function listModels() {
  try {
    const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models?key=${API_KEY}`);
    const data = await res.json();
    console.log(JSON.stringify(data, null, 2));
  } catch (err) {
    console.error(err);
  }
}

listModels();

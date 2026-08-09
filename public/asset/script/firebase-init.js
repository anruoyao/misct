import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-firestore.js";

// Replace only this portion  -- Start
const firebaseConfig = {
  apiKey: "AIzaSyC1-uyMqfQAz2V_5BmNalZkZYfLA_D07Cw",
  authDomain: "mishe-c586c.firebaseapp.com",
  projectId: "mishe-c586c",
  storageBucket: "mishe-c586c.firebasestorage.app",
  messagingSenderId: "937154847059",
  appId: "1:937154847059:web:3b88926569d56b0ed001a6",
  measurementId: "G-JMZG5Q5T24"
};
// Replace only this portion -- End

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

export {app, db};
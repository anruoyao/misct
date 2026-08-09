import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-firestore.js";

// Replace only this portion  -- Start
const firebaseConfig = {
  apiKey: "AIzaSyA_TyblB-WPhs-TNtyuaRhyCUlGH4wuzto",
  authDomain: "chatter-2ee95.firebaseapp.com",
  projectId: "chatter-2ee95",
  storageBucket: "chatter-2ee95.firebasestorage.app",
  messagingSenderId: "283720524531",
  appId: "1:283720524531:web:adb4b6045f15c939ea0d86",
  measurementId: "G-1V5XJGN13K",
};
// Replace only this portion -- End

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

export {app, db};
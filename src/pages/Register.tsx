import React, { useState } from "react";
import { createUserWithEmailAndPassword, signInWithPopup, GoogleAuthProvider } from "firebase/auth";
import { doc, setDoc, serverTimestamp, getDoc } from "firebase/firestore";
import { auth, db } from "../lib/firebase";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";

export function Register() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("user");
  const [error, setError] = useState("");
  const navigate = useNavigate();

  const handleGoogleLogin = async () => {
    const provider = new GoogleAuthProvider();
    try {
      const result = await signInWithPopup(auth, provider);
      const user = result.user;
      
      const docRef = doc(db, "users", user.uid);
      const docSnap = await getDoc(docRef);
      
      if (!docSnap.exists()) {
        await setDoc(docRef, {
          uid: user.uid,
          name: user.displayName || "Google User",
          email: user.email,
          role: "user",
          createdAt: serverTimestamp()
        });
      }
      
      navigate("/");
    } catch (err: any) {
      setError(err.message);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const userCredential = await createUserWithEmailAndPassword(auth, email, password);
      const user = userCredential.user;

      await setDoc(doc(db, "users", user.uid), {
        uid: user.uid,
        name,
        email,
        role,
        createdAt: serverTimestamp()
      });

      navigate("/");
    } catch (err: any) {
      if (err.code === "auth/email-already-in-use") {
        setError("This email is already registered. Please login instead.");
      } else if (err.code === "auth/weak-password") {
        setError("Password should be at least 6 characters.");
      } else if (err.code === "auth/invalid-email") {
        setError("Please enter a valid email address.");
      } else if (err.code === "auth/operation-not-allowed") {
        setError("Email/Password sign-in is not enabled. Please contact the administrator.");
      } else {
        setError(err.message);
      }
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-sn-dark p-4">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div className="bg-sn-sidebar p-8 text-white text-center">
          <div className="w-16 h-16 bg-sn-green rounded-xl flex items-center justify-center font-bold text-3xl text-sn-dark mx-auto mb-4 shadow-lg">C</div>
          <h1 className="text-2xl font-bold">Create Account</h1>
          <p className="text-text-dim text-sm mt-2">Join the Connect IT Portal</p>
        </div>
        
        <form onSubmit={handleRegister} className="p-8 space-y-4">
          {error && <div className="p-3 bg-red-50 text-red-600 text-sm rounded-md border border-red-100">{error}</div>}
          
          <div className="space-y-1">
            <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Full Name</label>
            <input 
              required
              value={name}
              onChange={e => setName(e.target.value)}
              className="w-full p-2.5 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
              placeholder="John Doe"
            />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Email Address</label>
            <input 
              type="email" 
              required
              value={email}
              onChange={e => setEmail(e.target.value)}
              className="w-full p-2.5 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
              placeholder="name@company.com"
            />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Password</label>
            <input 
              type="password" 
              required
              value={password}
              onChange={e => setPassword(e.target.value)}
              className="w-full p-2.5 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
              placeholder="••••••••"
            />
          </div>

          <div className="space-y-1">
            <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Role</label>
            <select 
              value={role}
              onChange={e => setRole(e.target.value)}
              className="w-full p-2.5 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
            >
              <option value="user">End User</option>
              <option value="agent">Support Agent</option>
              <option value="sub_admin">Sub Admin</option>
              <option value="admin">Administrator</option>
              <option value="super_admin">Super Admin</option>
              <option value="ultra_super_admin">Ultra Super Admin</option>
            </select>
          </div>

          <Button type="submit" className="w-full py-6 bg-sn-green text-sn-dark font-bold text-lg hover:bg-sn-green/90 shadow-md mt-4">
            Register
          </Button>

          <div className="relative my-4">
            <div className="absolute inset-0 flex items-center">
              <span className="w-full border-t border-border"></span>
            </div>
            <div className="relative flex justify-center text-xs uppercase">
              <span className="bg-white px-2 text-muted-foreground">Or continue with</span>
            </div>
          </div>

          <Button 
            type="button" 
            variant="outline" 
            onClick={handleGoogleLogin}
            className="w-full py-6 border-2 border-border font-bold flex items-center justify-center gap-2 hover:bg-muted/50"
          >
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" className="w-5 h-5" alt="Google" />
            Sign up with Google
          </Button>

          <p className="text-center text-sm text-muted-foreground">
            Already have an account? <Link to="/login" className="text-sn-green font-bold hover:underline">Login</Link>
          </p>
        </form>
      </div>
    </div>
  );
}

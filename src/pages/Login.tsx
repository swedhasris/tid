import React, { useState } from "react";
import { signInWithEmailAndPassword, signInWithPopup, GoogleAuthProvider } from "firebase/auth";
import { auth, db } from "../lib/firebase";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { doc, getDoc, setDoc, serverTimestamp } from "firebase/firestore";
import { useAuth } from "../contexts/AuthContext";

export function Login() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();
  const { demoLogin } = useAuth();

  const handleDemoLogin = async (role: 'admin' | 'agent' | 'user') => {
    setError("");
    setIsLoading(true);
    try {
      await demoLogin(role);
      navigate("/");
    } catch (err: any) {
      setError("Demo login failed: " + err.message);
    } finally {
      setIsLoading(false);
    }
  };

  const handleGoogleLogin = async () => {
    setError("");
    setIsLoading(true);
    const provider = new GoogleAuthProvider();
    try {
      const result = await signInWithPopup(auth, provider);
      const user = result.user;
      
      // Check if profile exists, if not create it
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
      if (err.code === "auth/popup-blocked") {
        setError("Popup was blocked by your browser. Please allow popups for this site and try again.");
      } else if (err.code === "auth/popup-closed-by-user") {
        setError("Sign-in popup was closed before completing.");
      } else if (err.code === "auth/cancelled-popup-request") {
        setError("");
      } else {
        setError(err.message);
      }
    } finally {
      setIsLoading(false);
    }
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) {
      setError("Please enter both email and password.");
      return;
    }
    setError("");
    setIsLoading(true);
    try {
      await signInWithEmailAndPassword(auth, email, password);
      navigate("/");
    } catch (err: any) {
      if (err.code === "auth/invalid-credential" || err.code === "auth/user-not-found" || err.code === "auth/wrong-password") {
        setError("Invalid email or password. Please try again.");
      } else if (err.code === "auth/too-many-requests") {
        setError("Too many failed attempts. Please try again later.");
      } else if (err.code === "auth/operation-not-allowed") {
        setError("Email/Password sign-in is not enabled. Please contact the administrator.");
      } else if (err.code === "auth/invalid-email") {
        setError("Please enter a valid email address.");
      } else if (err.code === "auth/network-request-failed") {
        setError("Network error. Please check your internet connection.");
      } else {
        setError(err.message);
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-sn-dark p-4">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div className="bg-sn-sidebar p-8 text-white text-center">
          <div className="w-16 h-16 bg-sn-green rounded-xl flex items-center justify-center font-bold text-3xl text-sn-dark mx-auto mb-4 shadow-lg">C</div>
          <h1 className="text-2xl font-bold">Connect IT</h1>
          <p className="text-text-dim text-sm mt-2">Sign in to your service portal</p>
        </div>
        
        <form onSubmit={handleLogin} className="p-8 space-y-6">
          {error && <div className="p-3 bg-red-50 text-red-600 text-sm rounded-md border border-red-100">{error}</div>}
          
          <div className="space-y-2">
            <label className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Email Address</label>
            <input 
              type="email" 
              required
              value={email}
              onChange={e => setEmail(e.target.value)}
              className="w-full p-3 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none transition-all"
              placeholder="name@company.com"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-semibold text-muted-foreground uppercase tracking-wider">Password</label>
            <input 
              type="password" 
              required
              value={password}
              onChange={e => setPassword(e.target.value)}
              className="w-full p-3 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none transition-all"
              placeholder="••••••••"
            />
          </div>

          <Button type="submit" disabled={isLoading} className="w-full py-6 bg-sn-green text-sn-dark font-bold text-lg hover:bg-sn-green/90 shadow-md disabled:opacity-50">
            {isLoading ? "Signing in..." : "Login"}
          </Button>

          <div className="relative my-6">
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
            disabled={isLoading}
            className="w-full py-6 border-2 border-border font-bold flex items-center justify-center gap-2 hover:bg-muted/50 disabled:opacity-50"
          >
            <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" className="w-5 h-5" alt="Google" />
            Sign in with Google
          </Button>

          <p className="text-center text-sm text-muted-foreground">
            Don't have an account? <Link to="/register" className="text-sn-green font-bold hover:underline">Register</Link>
          </p>

          <div className="mt-6 pt-6 border-t border-dashed border-border">
            <p className="text-xs text-center text-muted-foreground mb-3 uppercase tracking-wider">Demo Access (No Password Required)</p>
            <div className="grid grid-cols-3 gap-2">
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => handleDemoLogin('admin')}
                disabled={isLoading}
                className="py-3 text-xs font-bold bg-purple-50 border-purple-200 text-purple-700 hover:bg-purple-100"
              >
                Demo Admin
              </Button>
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => handleDemoLogin('agent')}
                disabled={isLoading}
                className="py-3 text-xs font-bold bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100"
              >
                Demo Agent
              </Button>
              <Button 
                type="button" 
                variant="outline" 
                onClick={() => handleDemoLogin('user')}
                disabled={isLoading}
                className="py-3 text-xs font-bold bg-green-50 border-green-200 text-green-700 hover:bg-green-100"
              >
                Demo User
              </Button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}

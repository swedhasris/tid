import React, { useState } from "react";
import { signInWithEmailAndPassword, signInWithPopup, GoogleAuthProvider } from "firebase/auth";
import { auth, db } from "../lib/firebase";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { doc, getDoc, setDoc, serverTimestamp } from "firebase/firestore";
import { ROLE_LABELS, ROLE_COLORS, type Role } from "../lib/roles";
import { Crown, Shield, UserCog, Eye } from "lucide-react";

const DEMO_ROLES: { role: Role; label: string; description: string; icon: any; color: string }[] = [
  { role: "user",              label: "User",              description: "End user — raise & track tickets",        icon: UserCog, color: "bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200" },
  { role: "agent",             label: "Agent",             description: "Support agent — manage incidents",         icon: UserCog, color: "bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100" },
  { role: "sub_admin",         label: "Sub Admin",         description: "Read-only company-wide visibility",        icon: Eye,     color: "bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100" },
  { role: "admin",             label: "Admin",             description: "Manage users, SLA & approvals",            icon: Shield,  color: "bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100" },
  { role: "super_admin",       label: "Super Admin",       description: "Manage dropdowns & system config",         icon: Crown,   color: "bg-red-50 text-red-700 border-red-200 hover:bg-red-100" },
  { role: "ultra_super_admin", label: "Ultra Super Admin", description: "Full control — grant/remove all access",   icon: Crown,   color: "bg-gradient-to-r from-yellow-50 to-orange-50 text-orange-800 border-orange-300 hover:from-yellow-100 hover:to-orange-100" },
];

export function Login() {
  const [email, setEmail]           = useState("");
  const [password, setPassword]     = useState("");
  const [error, setError]           = useState("");
  const [isLoading, setIsLoading]   = useState(false);
  const [demoLoading, setDemoLoading] = useState<Role | null>(null);
  const navigate = useNavigate();

  /* ── Demo login ─────────────────────────────────────────── */
  const handleDemoLogin = async (role: Role) => {
    setError("");
    setDemoLoading(role);
    try {
      const uid       = `demo_${role}_${Date.now()}`;
      const name      = `Demo ${ROLE_LABELS[role]}`;
      const emailAddr = `demo-${role}@connectit.local`;
      const demoProfile = { uid, name, email: emailAddr, role, isDemo: true };

      localStorage.setItem("demo_user", JSON.stringify(demoProfile));

      // Write to Firestore so data is shared across sessions
      try {
        await setDoc(doc(db, "users", uid), { ...demoProfile, createdAt: serverTimestamp() });
      } catch (_) { /* offline — localStorage session still works */ }

      window.location.href = "/";
    } catch (err: any) {
      setError("Demo login failed: " + err.message);
      setDemoLoading(null);
    }
  };

  /* ── Google login ───────────────────────────────────────── */
  const handleGoogleLogin = async () => {
    setError("");
    setIsLoading(true);
    try {
      const result = await signInWithPopup(auth, new GoogleAuthProvider());
      const user   = result.user;
      const ref    = doc(db, "users", user.uid);
      const snap   = await getDoc(ref);
      if (!snap.exists()) {
        await setDoc(ref, {
          uid: user.uid, name: user.displayName || "Google User",
          email: user.email, role: "user", createdAt: serverTimestamp(),
        });
      }
      navigate("/");
    } catch (err: any) {
      if (!["auth/popup-closed-by-user","auth/cancelled-popup-request"].includes(err.code))
        setError(err.code === "auth/popup-blocked" ? "Popup blocked — please allow popups." : err.message);
    } finally { setIsLoading(false); }
  };

  /* ── Email/password login ───────────────────────────────── */
  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) { setError("Please enter email and password."); return; }
    setError("");
    setIsLoading(true);
    try {
      await signInWithEmailAndPassword(auth, email, password);
      navigate("/");
    } catch (err: any) {
      const code = err.code;
      if (["auth/invalid-credential","auth/user-not-found","auth/wrong-password"].includes(code))
        setError("Invalid email or password.");
      else if (code === "auth/too-many-requests") setError("Too many attempts. Try again later.");
      else setError(err.message);
    } finally { setIsLoading(false); }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-sn-dark p-4">
      <div className="w-full max-w-4xl flex gap-6 items-start">

        {/* ── Login Form ── */}
        <div className="flex-1 bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div className="bg-sn-sidebar p-8 text-white text-center">
            <div className="w-16 h-16 bg-sn-green rounded-xl flex items-center justify-center font-bold text-3xl text-sn-dark mx-auto mb-4 shadow-lg">C</div>
            <h1 className="text-2xl font-bold">Connect IT</h1>
            <p className="text-white/60 text-sm mt-2">Sign in to your service portal</p>
          </div>

          <form onSubmit={handleLogin} className="p-8 space-y-5">
            {error && (
              <div className="p-3 bg-red-50 text-red-600 text-sm rounded-md border border-red-100">{error}</div>
            )}

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Email Address</label>
              <input type="email" required value={email} onChange={e => setEmail(e.target.value)}
                className="w-full p-3 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
                placeholder="name@company.com" />
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold text-muted-foreground uppercase tracking-wider">Password</label>
              <input type="password" required value={password} onChange={e => setPassword(e.target.value)}
                className="w-full p-3 border border-border rounded-lg focus:ring-2 focus:ring-sn-green outline-none"
                placeholder="••••••••" />
            </div>

            <Button type="submit" disabled={isLoading}
              className="w-full py-6 bg-sn-green text-sn-dark font-bold text-base hover:bg-sn-green/90 disabled:opacity-50">
              {isLoading ? "Signing in..." : "Sign In"}
            </Button>

            <div className="relative">
              <div className="absolute inset-0 flex items-center"><span className="w-full border-t border-border" /></div>
              <div className="relative flex justify-center text-xs uppercase">
                <span className="bg-white px-2 text-muted-foreground">Or</span>
              </div>
            </div>

            <Button type="button" variant="outline" onClick={handleGoogleLogin} disabled={isLoading}
              className="w-full py-5 border-2 font-bold flex items-center justify-center gap-2 hover:bg-muted/50">
              <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" className="w-5 h-5" alt="Google" />
              Sign in with Google
            </Button>

            <p className="text-center text-sm text-muted-foreground">
              No account? <Link to="/register" className="text-sn-green font-bold hover:underline">Register</Link>
            </p>
          </form>
        </div>

        {/* ── Demo Role Panel ── */}
        <div className="w-80 bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div className="bg-gradient-to-br from-sn-dark to-gray-800 p-6 text-white text-center">
            <div className="text-2xl mb-1">🚀</div>
            <h2 className="font-bold text-lg">Try a Demo Role</h2>
            <p className="text-white/60 text-xs mt-1">No password needed — instant access</p>
          </div>

          <div className="p-4 space-y-2.5">
            {DEMO_ROLES.map(({ role, label, description, icon: Icon, color }) => (
              <button key={role} onClick={() => handleDemoLogin(role)}
                disabled={demoLoading !== null}
                className={`w-full flex items-center gap-3 p-3 rounded-xl border text-left transition-all disabled:opacity-50 ${color}`}>
                <div className="w-9 h-9 rounded-lg bg-white/60 flex items-center justify-center flex-shrink-0">
                  <Icon className="w-4 h-4" />
                </div>
                <div className="flex-grow min-w-0">
                  <div className="font-bold text-sm leading-tight">
                    {demoLoading === role ? "Logging in..." : label}
                  </div>
                  <div className="text-[10px] opacity-70 leading-tight mt-0.5 truncate">{description}</div>
                </div>
                {demoLoading === role && (
                  <div className="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin flex-shrink-0" />
                )}
              </button>
            ))}

            <p className="text-[10px] text-center text-muted-foreground pt-2 border-t border-border">
              Demo sessions use localStorage auth.<br />All data is shared via Firestore.
            </p>
          </div>
        </div>

      </div>
    </div>
  );
}

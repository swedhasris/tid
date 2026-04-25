import React, { createContext, useContext, useEffect, useState } from "react";
import { onAuthStateChanged, User, signInAnonymously, signOut as firebaseSignOut } from "firebase/auth";
import { doc, onSnapshot, setDoc, serverTimestamp } from "firebase/firestore";
import { auth, db, handleFirestoreError, OperationType } from "../lib/firebase";

interface AuthContextType {
  user: User | null;
  profile: any | null;
  loading: boolean;
  demoLogin: (role: 'admin' | 'agent' | 'user') => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({ user: null, profile: null, loading: true });

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [profile, setProfile] = useState<any | null>(null);
  const [loading, setLoading] = useState(true);

  // Sync auth state to localStorage so standalone pages (timesheet) can read it
  useEffect(() => {
    if (user && profile) {
      localStorage.setItem('timesheet_user', JSON.stringify({
        uid: user.uid,
        name: profile.name || user.displayName || user.email?.split("@")[0] || "User",
        email: user.email,
        role: profile.role || 'user'
      }));
    } else if (!user) {
      localStorage.removeItem('timesheet_user');
    }
  }, [user, profile]);

  useEffect(() => {
    let unsubscribeProfile: (() => void) | null = null;

    // Check for demo user in localStorage first
    const demoUserStr = localStorage.getItem('demo_user');
    if (demoUserStr) {
      try {
        const demoUser = JSON.parse(demoUserStr);
        setUser({
          uid: demoUser.uid,
          email: demoUser.email,
          displayName: demoUser.name,
        } as User);
        setProfile(demoUser);
        setLoading(false);
        return () => {};
      } catch (e) {
        localStorage.removeItem('demo_user');
      }
    }

    const unsubscribeAuth = onAuthStateChanged(auth, (user) => {
      setUser(user);
      
      if (unsubscribeProfile) {
        unsubscribeProfile();
        unsubscribeProfile = null;
      }

      if (user) {
        const docRef = doc(db, "users", user.uid);
        unsubscribeProfile = onSnapshot(docRef, async (docSnap) => {
          if (docSnap.exists()) {
            setProfile(docSnap.data());
          } else {
            // Auto-create profile if missing
            console.log("Creating missing user profile for:", user.email);
            const initialProfile = {
              uid: user.uid,
              name: user.displayName || user.email?.split("@")[0] || "User",
              email: user.email,
              role: "user", // Default role
              createdAt: new Date().toISOString()
            };
            try {
              const { setDoc } = await import("firebase/firestore");
              await setDoc(docRef, initialProfile);
              setProfile(initialProfile);
            } catch (err) {
              console.error("Failed to auto-create profile:", err);
              setProfile(null);
            }
          }
          setLoading(false);
        }, (err) => {
          handleFirestoreError(err, OperationType.GET, `users/${user.uid}`);
          setLoading(false);
        });
      } else {
        setProfile(null);
        setLoading(false);
      }
    });

    return () => {
      unsubscribeAuth();
      if (unsubscribeProfile) unsubscribeProfile();
    };
  }, []);

  // Demo login function that works without Firebase Auth
  const demoLogin = async (role: 'admin' | 'agent' | 'user') => {
    try {
      // Try Firebase anonymous auth first
      const result = await signInAnonymously(auth);
      const user = result.user;
      
      // Create demo profile in Firestore
      const docRef = doc(db, "users", user.uid);
      await setDoc(docRef, {
        uid: user.uid,
        name: `Demo ${role.charAt(0).toUpperCase() + role.slice(1)}`,
        email: `demo-${role}@connectit.local`,
        role: role,
        createdAt: serverTimestamp()
      });
      
      // Also store in localStorage as backup
      localStorage.setItem('demo_user', JSON.stringify({
        uid: user.uid,
        name: `Demo ${role.charAt(0).toUpperCase() + role.slice(1)}`,
        email: `demo-${role}@connectit.local`,
        role: role
      }));
      
    } catch (err: any) {
      // If Firebase fails, use localStorage mock mode
      console.warn("Firebase auth failed, using local demo mode:", err);
      
      const mockUid = 'demo_' + role + '_' + Date.now();
      const mockUser = {
        uid: mockUid,
        name: `Demo ${role.charAt(0).toUpperCase() + role.slice(1)}`,
        email: `demo-${role}@connectit.local`,
        role: role,
        isDemo: true
      };
      
      localStorage.setItem('demo_user', JSON.stringify(mockUser));
      
      // Manually set the user state
      setUser({
        uid: mockUid,
        email: `demo-${role}@connectit.local`,
        displayName: `Demo ${role.charAt(0).toUpperCase() + role.slice(1)}`,
      } as User);
      setProfile(mockUser);
    }
  };
  
  const signOut = async () => {
    try {
      await firebaseSignOut(auth);
    } catch (err) {
      // Ignore errors
    }
    localStorage.removeItem('demo_user');
    localStorage.removeItem('timesheet_user');
    setUser(null);
    setProfile(null);
  };

  return (
    <AuthContext.Provider value={{ user, profile, loading, demoLogin, signOut }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);

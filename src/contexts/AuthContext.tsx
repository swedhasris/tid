import React, { createContext, useContext, useEffect, useState } from "react";
import { onAuthStateChanged, User, signInAnonymously, signOut as firebaseSignOut } from "firebase/auth";
import { doc, onSnapshot, setDoc, serverTimestamp } from "firebase/firestore";
import { auth, db, handleFirestoreError, OperationType } from "../lib/firebase";
import { Role, ROLE_HIERARCHY, Permissions } from "../lib/roles";

interface AuthContextType {
  user: User | null;
  profile: any | null;
  loading: boolean;
  role: Role;
  can: typeof Permissions;
  demoLogin: () => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  user: null, profile: null, loading: true,
  role: "user", can: Permissions,
  demoLogin: async () => {}, signOut: async () => {},
});

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
        // Accept any valid demo user regardless of role
        if (demoUser.uid && demoUser.role) {
          setUser({
            uid: demoUser.uid,
            email: demoUser.email,
            displayName: demoUser.name,
          } as User);
          setProfile(demoUser);
          setLoading(false);
          return () => {};
        }
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
  const demoLogin = async () => {
    const demoProfile = {
      name: "Demo",
      email: "demo-admin@connectit.local",
      role: "admin" as const
    };

    try {
      // Try Firebase anonymous auth first
      const result = await signInAnonymously(auth);
      const user = result.user;
      
      // Create demo profile in Firestore
      const docRef = doc(db, "users", user.uid);
      await setDoc(docRef, {
        uid: user.uid,
        name: demoProfile.name,
        email: demoProfile.email,
        role: demoProfile.role,
        createdAt: serverTimestamp()
      });
      
      // Also store in localStorage as backup
      localStorage.setItem('demo_user', JSON.stringify({
        uid: user.uid,
        name: demoProfile.name,
        email: demoProfile.email,
        role: demoProfile.role
      }));
      
    } catch (err: any) {
      // If Firebase fails, use localStorage mock mode
      console.warn("Firebase auth failed, using local demo mode:", err);
      
      const mockUid = 'demo_admin_' + Date.now();
      const mockUser = {
        uid: mockUid,
        name: demoProfile.name,
        email: demoProfile.email,
        role: demoProfile.role,
        isDemo: true
      };
      
      localStorage.setItem('demo_user', JSON.stringify(mockUser));
      
      // Manually set the user state
      setUser({
        uid: mockUid,
        email: demoProfile.email,
        displayName: demoProfile.name,
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
    <AuthContext.Provider value={{ user, profile, loading, role: (profile?.role || "user") as Role, can: Permissions, demoLogin, signOut }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);

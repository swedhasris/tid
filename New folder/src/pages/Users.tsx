import React, { useEffect, useState } from "react";
import { collection, query, onSnapshot, updateDoc, doc } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { ShieldAlert, UserCog, Mail, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";

export function Users() {
  const { profile } = useAuth();
  const [users, setUsers] = useState<any[]>([]);

  useEffect(() => {
    const q = query(collection(db, "users"));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      setUsers(snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() })));
    });
    return unsubscribe;
  }, []);

  const updateRole = async (userId: string, newRole: string) => {
    try {
      await updateDoc(doc(db, "users", userId), {
        role: newRole
      });
    } catch (error) {
      console.error("Error updating role:", error);
    }
  };

  if (profile?.role !== "admin") {
    return (
      <div className="flex flex-col items-center justify-center h-[60vh] text-center space-y-4">
        <ShieldAlert className="w-16 h-16 text-muted-foreground opacity-20" />
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators can manage users.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">User Management</h1>
        <p className="text-muted-foreground">Manage system users and their access levels.</p>
      </div>

      <div className="sn-card overflow-hidden p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-muted/50">
                <th className="data-table-header p-4">User</th>
                <th className="data-table-header p-4">Email</th>
                <th className="data-table-header p-4">Role</th>
                <th className="data-table-header p-4">Status</th>
                <th className="data-table-header p-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              {users.map((user) => (
                <tr key={user.id} className="data-table-row">
                  <td className="p-4">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-muted rounded-full flex items-center justify-center">
                        <UserCog className="w-4 h-4 text-muted-foreground" />
                      </div>
                      <span className="font-medium">{user.name}</span>
                    </div>
                  </td>
                  <td className="p-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-2">
                      <Mail className="w-3 h-3" /> {user.email}
                    </div>
                  </td>
                  <td className="p-4">
                    <select 
                      value={user.role} 
                      onChange={(e) => updateRole(user.id, e.target.value)}
                      className="bg-transparent border-none text-sm font-medium focus:ring-0 cursor-pointer"
                    >
                      <option value="user">User</option>
                      <option value="agent">Support Agent</option>
                      <option value="admin">Administrator</option>
                    </select>
                  </td>
                  <td className="p-4">
                    <span className="flex items-center gap-1 text-sn-green text-xs font-bold uppercase">
                      <ShieldCheck className="w-3 h-3" /> Active
                    </span>
                  </td>
                  <td className="p-4">
                    <Button variant="ghost" size="sm">Reset Password</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

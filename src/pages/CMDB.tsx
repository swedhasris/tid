import React, { useState, useEffect } from "react";
import { collection, onSnapshot, addDoc, serverTimestamp, query, orderBy } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Server, Laptop, Database, Globe, Shield, Activity, Search, Link as LinkIcon, X, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const getIcon = (type: string) => {
  switch (type) {
    case "Server": return Server;
    case "Database": return Database;
    case "Network": return Shield;
    case "Application": return Globe;
    default: return Laptop;
  }
};

export function CMDB() {
  const { profile } = useAuth();
  const [assets, setAssets] = useState<any[]>([]);
  const [search, setSearch] = useState("");
  const [showModal, setShowModal] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ name: "", type: "Server", status: "Operational", owner: "", location: "" });

  useEffect(() => {
    const q = query(collection(db, "cmdb_assets"), orderBy("createdAt", "desc"));
    return onSnapshot(q, snap => setAssets(snap.docs.map(d => ({ id: d.id, ...d.data() }))));
  }, []);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await addDoc(collection(db, "cmdb_assets"), {
        ...form,
        createdBy: profile?.uid || "",
        createdAt: serverTimestamp(),
        updatedAt: serverTimestamp(),
      });
      setShowModal(false);
      setForm({ name: "", type: "Server", status: "Operational", owner: "", location: "" });
    } catch (e) { console.error(e); }
    setSaving(false);
  };

  const filtered = assets.filter(a =>
    a.name?.toLowerCase().includes(search.toLowerCase()) ||
    a.type?.toLowerCase().includes(search.toLowerCase()) ||
    a.owner?.toLowerCase().includes(search.toLowerCase())
  );

  const operational = assets.filter(a => a.status === "Operational").length;
  const pct = assets.length ? Math.round((operational / assets.length) * 100) : 0;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-light text-sn-dark">Configuration Management (CMDB)</h1>
          <p className="text-muted-foreground">Manage and track all configuration items and their relationships.</p>
        </div>
        <div className="flex items-center gap-3">
          <Button className="bg-sn-green text-sn-dark font-bold gap-2" onClick={() => setShowModal(true)}>
            <Plus className="w-4 h-4" /> Add CI
          </Button>
        </div>
      </div>

      <div className="sn-card p-0 overflow-hidden">
        <div className="p-4 border-b border-border bg-muted/10 flex items-center justify-between gap-4">
          <div className="relative flex-grow max-w-md">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input type="text" placeholder="Search assets..." value={search} onChange={e => setSearch(e.target.value)}
              className="w-full bg-white border border-border rounded py-1.5 pl-9 pr-3 text-sm outline-none focus:ring-1 focus:ring-sn-green" />
          </div>
          <div className="text-xs text-muted-foreground">
            {filtered.length} of {assets.length} items · {pct}% operational
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-muted/30 border-b border-border">
                {["ID","Name","Type","Status","Owner","Location"].map(h => (
                  <th key={h} className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {filtered.length === 0 ? (
                <tr><td colSpan={6} className="p-12 text-center text-muted-foreground">
                  {assets.length === 0 ? "No assets yet. Add the first CI." : "No results found."}
                </td></tr>
              ) : filtered.map(asset => {
                const Icon = getIcon(asset.type);
                return (
                  <tr key={asset.id} className="hover:bg-muted/20 transition-colors">
                    <td className="p-4 text-sm font-mono text-blue-600">{asset.id?.slice(0,8)}</td>
                    <td className="p-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-muted rounded flex items-center justify-center">
                          <Icon className="w-4 h-4 text-sn-dark" />
                        </div>
                        <span className="text-sm font-medium text-sn-dark">{asset.name}</span>
                      </div>
                    </td>
                    <td className="p-4 text-sm text-muted-foreground">{asset.type}</td>
                    <td className="p-4">
                      <span className={cn("px-2 py-0.5 rounded-full text-[10px] font-bold uppercase",
                        asset.status === "Operational" ? "bg-green-100 text-green-700" :
                        asset.status === "Degraded" ? "bg-yellow-100 text-yellow-700" : "bg-red-100 text-red-700")}>
                        {asset.status}
                      </span>
                    </td>
                    <td className="p-4 text-sm text-muted-foreground">{asset.owner}</td>
                    <td className="p-4 text-sm text-muted-foreground">{asset.location}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" onClick={e => e.target === e.currentTarget && setShowModal(false)}>
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
            <div className="p-4 border-b border-border flex items-center justify-between bg-muted/30">
              <h3 className="font-bold">Add Configuration Item</h3>
              <button onClick={() => setShowModal(false)} className="p-1 hover:bg-muted rounded"><X className="w-5 h-5" /></button>
            </div>
            <form onSubmit={handleCreate} className="p-6 space-y-4">
              <div>
                <label className="text-sm font-medium block mb-1">Name <span className="text-red-500">*</span></label>
                <input required value={form.name} onChange={e => setForm(f => ({...f, name: e.target.value}))}
                  className="w-full p-2 border border-border rounded text-sm focus:ring-1 focus:ring-sn-green outline-none" />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium block mb-1">Type</label>
                  <select value={form.type} onChange={e => setForm(f => ({...f, type: e.target.value}))}
                    className="w-full p-2 border border-border rounded text-sm focus:ring-1 focus:ring-sn-green outline-none">
                    {["Server","Database","Network","Application","Hardware"].map(t => <option key={t}>{t}</option>)}
                  </select>
                </div>
                <div>
                  <label className="text-sm font-medium block mb-1">Status</label>
                  <select value={form.status} onChange={e => setForm(f => ({...f, status: e.target.value}))}
                    className="w-full p-2 border border-border rounded text-sm focus:ring-1 focus:ring-sn-green outline-none">
                    {["Operational","Degraded","Offline","Maintenance"].map(s => <option key={s}>{s}</option>)}
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium block mb-1">Owner</label>
                  <input value={form.owner} onChange={e => setForm(f => ({...f, owner: e.target.value}))}
                    className="w-full p-2 border border-border rounded text-sm focus:ring-1 focus:ring-sn-green outline-none" />
                </div>
                <div>
                  <label className="text-sm font-medium block mb-1">Location</label>
                  <input value={form.location} onChange={e => setForm(f => ({...f, location: e.target.value}))}
                    className="w-full p-2 border border-border rounded text-sm focus:ring-1 focus:ring-sn-green outline-none" />
                </div>
              </div>
              <div className="flex justify-end gap-3 pt-2 border-t border-border">
                <Button type="button" variant="outline" onClick={() => setShowModal(false)}>Cancel</Button>
                <Button type="submit" className="bg-sn-green text-sn-dark font-bold" disabled={saving}>
                  {saving ? "Saving..." : "Add CI"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

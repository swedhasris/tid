import React, { useEffect, useMemo, useState } from "react";
import { addDoc, collection, deleteDoc, doc, onSnapshot, orderBy, query, serverTimestamp } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { ROLE_HIERARCHY } from "../lib/roles";
import { 
  ShieldAlert, Zap, Plus, Trash2, Settings2, Layers, List, Tag, Users, 
  ChevronRight, Layout, Bell, Shield, Activity, Database, Search, Filter,
  ArrowRight, Info
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { CategoryItem, GroupItem, ServiceItem, SubcategoryItem } from "../lib/serviceCatalog";

export function Settings() {
  const { role } = useAuth();
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [activeTab, setActiveTab] = useState<"catalog" | "automation" | "security">("catalog");

  const [categories, setCategories] = useState<CategoryItem[]>([]);
  const [subcategories, setSubcategories] = useState<SubcategoryItem[]>([]);
  const [services, setServices] = useState<ServiceItem[]>([]);
  const [groups, setGroups] = useState<GroupItem[]>([]);

  // Selection states for Miller Columns
  const [selectedCatId, setSelectedCatId] = useState<string | null>(null);
  const [selectedSubId, setSelectedSubId] = useState<string | null>(null);

  // Form states
  const [newCatName, setNewCatName] = useState("");
  const [newSubName, setNewSubName] = useState("");
  const [newSrvName, setNewSrvName] = useState("");
  const [newProvider, setNewProvider] = useState("");

  const isAdmin = ROLE_HIERARCHY[role] >= ROLE_HIERARCHY["admin"];

  useEffect(() => {
    const unsubscribers = [
      onSnapshot(query(collection(db, "settings_categories"), orderBy("createdAt", "asc")), (snap) => {
        setCategories(snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<CategoryItem, "id">) })).filter((item) => item.isActive !== false));
      }),
      onSnapshot(query(collection(db, "settings_subcategories"), orderBy("createdAt", "asc")), (snap) => {
        setSubcategories(snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<SubcategoryItem, "id">) })).filter((item) => item.isActive !== false));
      }),
      onSnapshot(query(collection(db, "settings_services"), orderBy("createdAt", "asc")), (snap) => {
        setServices(snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<ServiceItem, "id">) })).filter((item) => item.isActive !== false));
      }),
      onSnapshot(query(collection(db, "settings_groups"), orderBy("createdAt", "asc")), (snap) => {
        setGroups(snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<GroupItem, "id">) })).filter((item) => item.isActive !== false));
      }),
    ];
    return () => unsubscribers.forEach((unsubscribe) => unsubscribe());
  }, []);

  const filteredCategories = useMemo(
    () => categories.filter((item) => !["cmdb", "it infrastructure"].includes((item.name || "").toLowerCase())),
    [categories]
  );

  const filteredSubcategories = useMemo(
    () => subcategories.filter(s => s.categoryId === selectedCatId),
    [subcategories, selectedCatId]
  );

  const filteredServices = useMemo(
    () => services.filter(s => s.subcategoryId === selectedSubId),
    [services, selectedSubId]
  );

  const handleDelete = async (collectionName: string, id: string) => {
    if (!confirm("Delete this item? This action is permanent.")) return;
    await deleteDoc(doc(db, collectionName, id));
    if (collectionName === 'settings_categories' && id === selectedCatId) setSelectedCatId(null);
    if (collectionName === 'settings_subcategories' && id === selectedSubId) setSelectedSubId(null);
  };

  const triggerEscalation = async () => {
    setLoading(true);
    setMessage("");
    try {
      const res = await fetch("/api/tickets/trigger-escalation", { method: "POST" });
      const data = await res.json();
      setMessage(data.message || "Escalation scan completed.");
    } catch {
      setMessage("Escalation triggered.");
    }
    setLoading(false);
  };

  if (!isAdmin) {
    return (
      <div className="flex flex-col items-center justify-center h-[70vh] text-center p-8">
        <div className="w-24 h-24 bg-red-50 dark:bg-red-900/10 rounded-full flex items-center justify-center mb-6">
          <ShieldAlert className="w-12 h-12 text-red-500" />
        </div>
        <h2 className="text-3xl font-black text-sn-dark dark:text-white">Access Denied</h2>
        <p className="text-muted-foreground mt-2 max-w-sm">System configurations are restricted to administrators. Please contact your supervisor for access.</p>
      </div>
    );
  }

  return (
    <div className="max-w-[1600px] mx-auto space-y-8 animate-in fade-in duration-700">
      
      {/* ── Page Header ── */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b border-border">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-sn-green mb-1">
            <Settings2 size={16} />
            <span className="text-[10px] font-black uppercase tracking-[0.2em]">Platform Admin</span>
          </div>
          <h1 className="text-4xl font-black text-sn-dark dark:text-white tracking-tight">System Settings</h1>
          <p className="text-muted-foreground font-medium">Manage the global IT service catalog and automation logic.</p>
        </div>
        
        <div className="flex bg-muted/50 p-1.5 rounded-2xl border border-border">
          {[
            { id: "catalog", label: "Catalog", icon: Layers },
            { id: "automation", label: "Workflows", icon: Zap },
            { id: "security", label: "Security", icon: Shield },
          ].map(t => (
            <button
              key={t.id}
              onClick={() => setActiveTab(t.id as any)}
              className={cn(
                "flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold transition-all",
                activeTab === t.id 
                  ? "bg-white dark:bg-gray-800 shadow-lg text-sn-dark dark:text-white" 
                  : "text-muted-foreground hover:text-sn-dark dark:hover:text-white"
              )}
            >
              <t.icon size={16} className={activeTab === t.id ? "text-sn-green" : ""} />
              {t.label}
            </button>
          ))}
        </div>
      </div>

      {activeTab === "catalog" && (
        <div className="space-y-6">
          <div className="bg-sn-dark text-white p-6 rounded-3xl flex items-center justify-between overflow-hidden relative">
            <div className="relative z-10 space-y-1">
              <h2 className="text-xl font-bold flex items-center gap-2">
                <Info size={20} className="text-sn-green" />
                Service Catalog Architect
              </h2>
              <p className="text-white/60 text-sm">Design the hierarchy from Main Categories down to Team Assignments.</p>
            </div>
            <div className="absolute right-0 top-0 opacity-10 pointer-events-none">
              <Layout size={200} />
            </div>
          </div>

          {/* ── Miller Columns (Visual Explorer) ── */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[600px]">
            
            {/* Column 1: Main Categories */}
            <div className="flex flex-col bg-white dark:bg-gray-900 border border-border rounded-3xl overflow-hidden shadow-sm">
              <div className="p-5 border-b border-border bg-muted/20 flex items-center justify-between">
                <span className="font-black text-xs uppercase tracking-widest flex items-center gap-2">
                  <Layers size={14} className="text-sn-green" /> Categories
                </span>
                <span className="text-[10px] font-bold bg-sn-dark text-white px-2 py-0.5 rounded-full">{filteredCategories.length}</span>
              </div>
              <div className="flex-1 overflow-y-auto p-3 space-y-1">
                {filteredCategories.map(cat => (
                  <button
                    key={cat.id}
                    onClick={() => { setSelectedCatId(cat.id); setSelectedSubId(null); }}
                    className={cn(
                      "w-full flex items-center justify-between p-3.5 rounded-2xl group transition-all text-left",
                      selectedCatId === cat.id ? "bg-sn-green/10 border border-sn-green/20" : "hover:bg-muted/50 border border-transparent"
                    )}
                  >
                    <span className={cn("text-sm font-bold", selectedCatId === cat.id ? "text-sn-green" : "text-sn-dark dark:text-gray-200")}>{cat.name}</span>
                    <div className="flex items-center gap-2">
                      <Trash2 onClick={(e) => { e.stopPropagation(); handleDelete('settings_categories', cat.id); }} size={14} className="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 transition-opacity" />
                      <ChevronRight size={16} className={cn("transition-transform", selectedCatId === cat.id ? "translate-x-1 text-sn-green" : "text-muted-foreground")} />
                    </div>
                  </button>
                ))}
              </div>
              <form 
                onSubmit={async (e) => {
                  e.preventDefault();
                  if (!newCatName.trim()) return;
                  await addDoc(collection(db, "settings_categories"), { name: newCatName.trim(), isActive: true, createdAt: serverTimestamp() });
                  setNewCatName("");
                }}
                className="p-4 border-t border-border flex gap-2"
              >
                <input value={newCatName} onChange={e => setNewCatName(e.target.value)} className="flex-1 bg-muted/50 border-none rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-sn-green/20" placeholder="New category..." />
                <button type="submit" className="bg-sn-dark text-white p-2.5 rounded-xl hover:bg-black transition-colors"><Plus size={18} /></button>
              </form>
            </div>

            {/* Column 2: Subcategories */}
            <div className={cn("flex flex-col bg-white dark:bg-gray-900 border border-border rounded-3xl overflow-hidden shadow-sm transition-opacity", !selectedCatId && "opacity-30 pointer-events-none")}>
              <div className="p-5 border-b border-border bg-muted/20 flex items-center justify-between">
                <span className="font-black text-xs uppercase tracking-widest flex items-center gap-2">
                  <List size={14} className="text-sn-green" /> Subcategories
                </span>
                <span className="text-[10px] font-bold bg-sn-dark text-white px-2 py-0.5 rounded-full">{filteredSubcategories.length}</span>
              </div>
              <div className="flex-1 overflow-y-auto p-3 space-y-1">
                {filteredSubcategories.map(sub => (
                  <button
                    key={sub.id}
                    onClick={() => setSelectedSubId(sub.id)}
                    className={cn(
                      "w-full flex items-center justify-between p-3.5 rounded-2xl group transition-all text-left",
                      selectedSubId === sub.id ? "bg-sn-green/10 border border-sn-green/20" : "hover:bg-muted/50 border border-transparent"
                    )}
                  >
                    <span className={cn("text-sm font-bold", selectedSubId === sub.id ? "text-sn-green" : "text-sn-dark dark:text-gray-200")}>{sub.name}</span>
                    <div className="flex items-center gap-2">
                      <Trash2 onClick={(e) => { e.stopPropagation(); handleDelete('settings_subcategories', sub.id); }} size={14} className="opacity-0 group-hover:opacity-100 text-red-400 hover:text-red-600 transition-opacity" />
                      <ChevronRight size={16} className={cn("transition-transform", selectedSubId === sub.id ? "translate-x-1 text-sn-green" : "text-muted-foreground")} />
                    </div>
                  </button>
                ))}
                {!selectedCatId && <div className="flex flex-col items-center justify-center h-full text-center p-6 text-muted-foreground"><Layers size={32} className="mb-2 opacity-20" /><p className="text-xs">Select a category to view subcategories</p></div>}
              </div>
              <form 
                onSubmit={async (e) => {
                  e.preventDefault();
                  if (!newSubName.trim() || !selectedCatId) return;
                  const cat = categories.find(c => c.id === selectedCatId);
                  await addDoc(collection(db, "settings_subcategories"), { name: newSubName.trim(), categoryId: selectedCatId, categoryName: cat?.name || "", isActive: true, createdAt: serverTimestamp() });
                  setNewSubName("");
                }}
                className="p-4 border-t border-border flex gap-2"
              >
                <input value={newSubName} onChange={e => setNewSubName(e.target.value)} className="flex-1 bg-muted/50 border-none rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-sn-green/20" placeholder="New subcategory..." />
                <button type="submit" className="bg-sn-dark text-white p-2.5 rounded-xl hover:bg-black transition-colors"><Plus size={18} /></button>
              </form>
            </div>

            {/* Column 3: Services & Team Assignments */}
            <div className={cn("flex flex-col bg-white dark:bg-gray-900 border border-border rounded-3xl overflow-hidden shadow-sm transition-opacity", !selectedSubId && "opacity-30 pointer-events-none")}>
              <div className="p-5 border-b border-border bg-muted/20 flex items-center justify-between">
                <span className="font-black text-xs uppercase tracking-widest flex items-center gap-2">
                  <Tag size={14} className="text-sn-green" /> Services & Teams
                </span>
              </div>
              <div className="flex-1 overflow-y-auto p-5 space-y-6">
                
                {/* Services Section */}
                <div className="space-y-3">
                  <h4 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground flex items-center gap-2">
                    <Layout size={12} /> Active Services
                  </h4>
                  <div className="grid grid-cols-1 gap-2">
                    {filteredServices.map(srv => (
                      <div key={srv.id} className="p-3 bg-muted/30 rounded-2xl flex items-center justify-between group">
                        <div>
                          <div className="text-sm font-bold text-sn-dark dark:text-white">{srv.name}</div>
                          <div className="text-[10px] font-medium text-muted-foreground uppercase">{srv.providerName}</div>
                        </div>
                        <button onClick={() => handleDelete('settings_services', srv.id)} className="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:text-red-600 transition-all">
                          <Trash2 size={14} />
                        </button>
                      </div>
                    ))}
                  </div>
                  <form 
                    onSubmit={async (e) => {
                      e.preventDefault();
                      const sub = subcategories.find(s => s.id === selectedSubId);
                      const cat = categories.find(c => c.id === sub?.categoryId);
                      if (!newSrvName.trim() || !newProvider.trim() || !sub || !cat) return;
                      await addDoc(collection(db, "settings_services"), {
                        name: newSrvName.trim(), providerName: newProvider.trim(),
                        categoryId: cat.id, categoryName: cat.name, subcategoryId: sub.id, subcategoryName: sub.name,
                        isActive: true, createdAt: serverTimestamp()
                      });
                      setNewSrvName(""); setNewProvider("");
                    }}
                    className="grid grid-cols-1 gap-2 p-3 bg-muted/20 rounded-2xl border border-dashed border-border"
                  >
                    <input value={newSrvName} onChange={e => setNewSrvName(e.target.value)} className="bg-transparent text-sm font-bold outline-none border-b border-border/50 pb-1" placeholder="Service Name" />
                    <div className="flex items-center gap-2">
                      <input value={newProvider} onChange={e => setNewProvider(e.target.value)} className="flex-1 bg-transparent text-xs outline-none" placeholder="Provider (e.g. AWS)" />
                      <button type="submit" className="text-sn-green hover:scale-110 transition-transform"><Plus size={20} /></button>
                    </div>
                  </form>
                </div>

                {/* Team Assignment (Simplified for Column) */}
                <div className="space-y-3">
                  <h4 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground flex items-center gap-2">
                    <Users size={12} /> Assigned Groups
                  </h4>
                  {groups.filter(g => g.subcategoryIds.includes(selectedSubId || "")).map(g => (
                    <div key={g.id} className="p-4 border border-border rounded-2xl flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-sn-dark text-white flex items-center justify-center text-[10px] font-black">
                          {g.name.slice(0, 2).toUpperCase()}
                        </div>
                        <div>
                          <div className="text-sm font-bold">{g.name}</div>
                          <div className="text-[10px] text-muted-foreground">{g.members.length} Members</div>
                        </div>
                      </div>
                      <button onClick={() => handleDelete('settings_groups', g.id)} className="text-muted-foreground hover:text-red-500 transition-colors"><Trash2 size={14} /></button>
                    </div>
                  ))}
                </div>

              </div>
            </div>

          </div>
        </div>
      )}

      {activeTab === "automation" && (
        <div className="max-w-4xl mx-auto space-y-8 animate-in slide-in-from-bottom-6 duration-700">
          <div className="bg-white dark:bg-gray-900 border border-border rounded-[40px] p-12 shadow-xl relative overflow-hidden group">
            <div className="absolute -right-20 -top-20 w-64 h-64 bg-sn-green/5 rounded-full blur-3xl group-hover:scale-150 transition-transform duration-1000" />
            
            <div className="relative z-10 text-center max-w-2xl mx-auto space-y-6">
              <div className="w-24 h-24 bg-sn-dark text-sn-green rounded-3xl flex items-center justify-center mx-auto shadow-2xl shadow-sn-green/20">
                <Activity size={48} />
              </div>
              <h2 className="text-4xl font-black text-sn-dark dark:text-white tracking-tight">SLA Escalation Suite</h2>
              <p className="text-muted-foreground text-lg leading-relaxed font-medium">
                Our proprietary automation engine monitors every ticket against global resolution targets. When a deadline is breached, we automatically escalate to the assigned group leaders.
              </p>
              
              <div className="pt-8 flex flex-col items-center gap-4">
                <Button 
                  onClick={triggerEscalation} 
                  disabled={loading}
                  className="bg-sn-green text-sn-dark h-16 px-12 rounded-full font-black text-lg shadow-xl shadow-sn-green/30 hover:scale-105 active:scale-95 transition-all w-full md:w-auto"
                >
                  {loading ? "Processing Real-time Scan..." : "Trigger Global Scan Now"}
                </Button>
                {message && (
                  <div className="animate-bounce-in bg-sn-dark text-white px-6 py-3 rounded-2xl text-sm font-bold flex items-center gap-3">
                    <Zap size={16} className="text-sn-green" /> {message}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-muted/30 p-8 rounded-3xl border border-border group hover:bg-white dark:hover:bg-gray-800 transition-all">
              <div className="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-500 flex items-center justify-center mb-4"><Bell size={24} /></div>
              <h3 className="text-xl font-bold mb-2">Notification Center</h3>
              <p className="text-muted-foreground text-sm leading-relaxed mb-6">Manage global alerts for ticket creation, closure, and critical infrastructure failures.</p>
              <Button variant="outline" disabled className="rounded-xl font-bold uppercase tracking-widest text-[10px]">Configure Alerts</Button>
            </div>
            <div className="bg-muted/30 p-8 rounded-3xl border border-border group hover:bg-white dark:hover:bg-gray-800 transition-all">
              <div className="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center mb-4"><Database size={24} /></div>
              <h3 className="text-xl font-bold mb-2">Audit Registry</h3>
              <p className="text-muted-foreground text-sm leading-relaxed mb-6">Trace every configuration change back to the administrator who performed it for compliance.</p>
              <Button variant="outline" disabled className="rounded-xl font-bold uppercase tracking-widest text-[10px]">Review Logs</Button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}

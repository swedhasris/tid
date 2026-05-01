import React, { useEffect, useMemo, useState } from "react";
import { addDoc, collection, deleteDoc, doc, onSnapshot, orderBy, query, serverTimestamp, updateDoc, getDocs, where } from "firebase/firestore";
import { db } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { ROLE_HIERARCHY, Role } from "../lib/roles";
import { 
  ShieldAlert, Zap, Plus, Trash2, Settings2, Layers, List, Tag, Users, 
  ChevronRight, Layout, Bell, Shield, Activity, Database, Search, Filter,
  ArrowRight, Info, Lock, Globe, Cpu, Radio, Sparkles, Box, HardDrive,
  Edit3, Eye, Clock, UserPlus, UserMinus, AlertCircle, CheckCircle2, History
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { 
  CategoryItem, SubcategoryItem, ServiceProviderItem, GroupItem, GroupMemberItem, 
  AuditLog, Status, useServiceCatalog 
} from "../lib/serviceCatalog";
import { motion, AnimatePresence } from "framer-motion";
import { AreaChart, Area, ResponsiveContainer } from "recharts";
import { format } from "date-fns";

const MOCK_TRAFFIC_DATA = [
  { time: "00:00", requests: 400 },
  { time: "04:00", requests: 300 },
  { time: "08:00", requests: 900 },
  { time: "12:00", requests: 1200 },
  { time: "16:00", requests: 1500 },
  { time: "20:00", requests: 800 },
  { time: "23:59", requests: 500 },
];

export function Settings() {
  const { user, profile, role } = useAuth();
  const { categories, subcategories, serviceProviders, groups, members } = useServiceCatalog();
  
  const [activeTab, setActiveTab] = useState<"master" | "automation" | "security" | "audit">("master");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<{ text: string, type: 'success' | 'error' } | null>(null);

  // Selection states for hierarchy
  const [selectedCatId, setSelectedCatId] = useState<string | null>(null);
  const [selectedSubId, setSelectedSubId] = useState<string | null>(null);
  const [selectedSrvId, setSelectedSrvId] = useState<string | null>(null);
  const [selectedGroupId, setSelectedGroupId] = useState<string | null>(null);

  // Modal / Form states
  const [editingItem, setEditingItem] = useState<{ type: string, data: any } | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [allUsers, setAllUsers] = useState<any[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLog[]>([]);

  const isAdmin = ROLE_HIERARCHY[role] >= ROLE_HIERARCHY["admin"];

  useEffect(() => {
    if (isAdmin) {
      // Fetch users for member management
      getDocs(collection(db, "users")).then(snap => {
        setAllUsers(snap.docs.map(d => ({ id: d.id, ...d.data() })));
      });
      // Fetch audit logs
      onSnapshot(query(collection(db, "settings_audit_logs"), orderBy("timestamp", "desc")), snap => {
        setAuditLogs(snap.docs.map(d => ({ id: d.id, ...d.data() } as AuditLog)));
      });
    }
  }, [isAdmin]);

  const createAuditLog = async (moduleId: string, moduleName: string, action: AuditLog['action'], oldVal: any, newVal: any) => {
    await addDoc(collection(db, "settings_audit_logs"), {
      moduleId,
      moduleName,
      action,
      oldValue: oldVal || null,
      newValue: newVal || null,
      performedBy: user?.email || "Unknown",
      performedByRole: role,
      timestamp: serverTimestamp()
    });
  };

  const handleMutation = async (type: string, action: 'create' | 'update' | 'delete', data?: any) => {
    if (!isAdmin) return;
    setLoading(true);
    try {
      // Map type to exact Firestore collection name
      const collectionMap: Record<string, string> = {
        'Category': 'settings_categories',
        'Subcategory': 'settings_subcategories',
        'Service Provider': 'settings_service_providers',
        'Group': 'settings_groups',
        'Group Member': 'settings_group_members',
      };
      const collectionName = collectionMap[type];
      if (!collectionName) throw new Error(`Unknown type: ${type}`);

      let id = data?.id;

      if (action === 'delete') {
        if (!confirm("Are you sure? This will mark the item as inactive.")) return;
        await updateDoc(doc(db, collectionName, id), { status: 'inactive', updatedAt: serverTimestamp() });
        await createAuditLog(id, type, 'delete', data, { ...data, status: 'inactive' });
      } else {
        // Only check for duplicates against CURRENTLY VISIBLE (active) items in UI
        if (action === 'create') {
          const visibleItems: any[] =
            type === 'Category' ? activeCategories :
            type === 'Subcategory' ? activeSubcategories :
            type === 'Service Provider' ? activeProviders :
            type === 'Group' ? activeGroups :
            type === 'Group Member' ? activeMembers : [];

          const nameToCheck = (data.name || "").trim().toLowerCase();
          const userIdToCheck = data.userId;

          const duplicate = visibleItems.find((item: any) => {
            if (type === 'Group Member') return item.userId === userIdToCheck;
            return (item.name || "").trim().toLowerCase() === nameToCheck;
          });

          if (duplicate) {
            throw new Error(`A ${type} named "${data.name || data.userName}" already exists.`);
          }
        }

        if (action === 'update') {
          await updateDoc(doc(db, collectionName, id), { ...data.new, updatedAt: serverTimestamp() });
          await createAuditLog(id, type, 'update', data.old, data.new);
        } else if (action === 'create') {
          const docRef = await addDoc(collection(db, collectionName), {
            ...data,
            status: 'active',
            createdAt: serverTimestamp(),
            createdBy: user?.email || 'demo'
          });
          await createAuditLog(docRef.id, type, 'create', null, data);
        }
      }
      setMessage({ text: `${type} saved successfully!`, type: 'success' });
      setIsModalOpen(false);
      setEditingItem(null);
    } catch (err: any) {
      setMessage({ text: err.message, type: 'error' });
    }
    setLoading(false);
    setTimeout(() => setMessage(null), 5000);
  };

  // Filtered views
  const activeCategories = useMemo(() => categories.filter(c => c.status === 'active'), [categories]);
  const activeSubcategories = useMemo(() => subcategories.filter(s => s.categoryId === selectedCatId && s.status === 'active'), [subcategories, selectedCatId]);
  const activeProviders = useMemo(() => serviceProviders.filter(p => p.subcategoryId === selectedSubId && p.status === 'active'), [serviceProviders, selectedSubId]);
  const activeGroups = useMemo(() => groups.filter(g => g.serviceProviderId === selectedSrvId && g.status === 'active'), [groups, selectedSrvId]);
  const activeMembers = useMemo(() => members.filter(m => m.groupId === selectedGroupId && m.status === 'active'), [members, selectedGroupId]);

  const handleSeedData = async () => {
    if (!isAdmin || !confirm("This will populate demo hierarchy data. Continue?")) return;
    setLoading(true);
    try {
      // 1. Category
      const catRef = await addDoc(collection(db, "settings_categories"), { name: "IT Support", status: "active", createdAt: serverTimestamp() });
      // 2. Subcategory
      const subRef = await addDoc(collection(db, "settings_subcategories"), { name: "Software Issue", categoryId: catRef.id, status: "active", createdAt: serverTimestamp() });
      // 3. Provider
      const provRef = await addDoc(collection(db, "settings_service_providers"), { name: "Adobe Systems", subcategoryId: subRef.id, sla: "4h", status: "active", createdAt: serverTimestamp() });
      // 4. Group
      const groupRef = await addDoc(collection(db, "settings_groups"), { name: "Design Software Team", serviceProviderId: provRef.id, status: "active", createdAt: serverTimestamp() });
      // 5. Member
      if (allUsers.length > 0) {
        await addDoc(collection(db, "settings_group_members"), { 
          userId: allUsers[0].id, 
          userName: allUsers[0].name || allUsers[0].email, 
          groupId: groupRef.id, 
          roleInGroup: "agent", 
          status: "active", 
          createdAt: serverTimestamp() 
        });
      }
      setMessage({ text: "Demo data seeded successfully!", type: "success" });
    } catch (err: any) {
      setMessage({ text: err.message, type: "error" });
    } finally {
      setLoading(false);
      setTimeout(() => setMessage(null), 3000);
    }
  };

  return (
    <div className="max-w-[1600px] mx-auto min-h-[90vh] flex flex-col gap-6 pb-20">
      
      {/* ── Dynamic Header ── */}
      <div className="relative p-12 bg-sn-sidebar rounded-[40px] border border-white/5 shadow-2xl overflow-hidden group flex flex-col items-center text-center">
        <div className="absolute inset-0 bg-gradient-to-b from-sn-green/5 to-transparent pointer-events-none" />
        <div className="absolute -right-20 -top-20 w-96 h-96 bg-sn-green/10 rounded-full blur-[100px] group-hover:scale-125 transition-transform duration-1000" />
        <div className="relative z-10 space-y-6 max-w-2xl w-full">
          <div className="space-y-2 flex flex-col items-center">
            <div className="flex items-center justify-between w-full mb-4">
              <div className="flex items-center gap-2 text-sn-green">
                <div className="w-6 h-6 rounded-lg bg-sn-green/10 flex items-center justify-center">
                  <Settings2 size={14} />
                </div>
                <span className="text-[10px] font-black uppercase tracking-[0.4em]">Master Infrastructure</span>
              </div>
              {isAdmin && (
                <Button 
                  onClick={handleSeedData} 
                  variant="outline" 
                  size="sm" 
                  className="h-8 border-sn-green/20 text-sn-green hover:bg-sn-green/10 text-[10px] font-black uppercase tracking-widest px-4 rounded-xl"
                >
                  <Sparkles size={12} className="mr-2" /> Seed Demo Data
                </Button>
              )}
            </div>
            <h1 className="text-6xl font-black text-white tracking-tighter">Platform Architect</h1>
            <p className="text-text-dim font-medium text-lg mx-auto">Centralized command for global service hierarchies and RBAC.</p>
          </div>
          
          <div className="flex bg-black/40 p-1.5 rounded-[22px] border border-white/5 backdrop-blur-xl inline-flex">
            {[
              { id: "master", label: "Hierarchy", icon: Layers },
              { id: "automation", label: "Workflows", icon: Zap },
              { id: "security", label: "Security", icon: Shield },
              { id: "audit", label: "Audit", icon: History },
            ].map(t => (
              <button
                key={t.id}
                onClick={() => setActiveTab(t.id as any)}
                className={cn(
                  "flex items-center gap-2.5 px-6 py-3 rounded-[18px] text-sm font-black transition-all duration-500",
                  activeTab === t.id ? "bg-sn-green text-sn-dark shadow-[0_0_20px_rgba(129,181,50,0.3)]" : "text-text-dim hover:text-white"
                )}
              >
                <t.icon size={16} /> {t.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <AnimatePresence mode="wait">
        <motion.div
          key={activeTab}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          className="flex-1"
        >
          {activeTab === "master" && (
            <div className="space-y-8">
              {/* Row 1: High Level Definition (3 Columns) */}
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[600px]">
                <MasterColumn 
                  title="Categories" 
                  icon={Globe} 
                  items={activeCategories} 
                  selectedId={selectedCatId} 
                  onSelect={(id) => { setSelectedCatId(id); setSelectedSubId(null); setSelectedSrvId(null); setSelectedGroupId(null); }}
                  onAdd={() => { setEditingItem({ type: 'Category', data: {} }); setIsModalOpen(true); }}
                  onEdit={(item) => { setEditingItem({ type: 'Category', data: item }); setIsModalOpen(true); }}
                  onDelete={(item) => handleMutation('Category', 'delete', item)}
                  isAdmin={isAdmin}
                />
                <MasterColumn 
                  title="Sub-Categories" 
                  icon={Radio} 
                  items={activeSubcategories} 
                  selectedId={selectedSubId} 
                  disabled={!selectedCatId}
                  onSelect={(id) => { setSelectedSubId(id); setSelectedSrvId(null); setSelectedGroupId(null); }}
                  onAdd={() => { setEditingItem({ type: 'Subcategory', data: { categoryId: selectedCatId } }); setIsModalOpen(true); }}
                  onEdit={(item) => { setEditingItem({ type: 'Subcategory', data: item }); setIsModalOpen(true); }}
                  onDelete={(item) => handleMutation('Subcategory', 'delete', item)}
                  isAdmin={isAdmin}
                />
                <MasterColumn 
                  title="Providers" 
                  icon={Box} 
                  items={activeProviders} 
                  selectedId={selectedSrvId} 
                  disabled={!selectedSubId}
                  onSelect={(id) => { setSelectedSrvId(id); setSelectedGroupId(null); }}
                  onAdd={() => { setEditingItem({ type: 'Service Provider', data: { subcategoryId: selectedSubId, categoryId: selectedCatId } }); setIsModalOpen(true); }}
                  onEdit={(item) => { setEditingItem({ type: 'Service Provider', data: item }); setIsModalOpen(true); }}
                  onDelete={(item) => handleMutation('Service Provider', 'delete', item)}
                  isAdmin={isAdmin}
                />
              </div>

              {/* Row 2: Operational Structures (2 Columns) */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[600px] max-w-5xl mx-auto">
                <MasterColumn 
                  title="Groups" 
                  icon={Users} 
                  items={activeGroups} 
                  selectedId={selectedGroupId} 
                  disabled={!selectedSrvId}
                  onSelect={(id) => setSelectedGroupId(id)}
                  onAdd={() => { setEditingItem({ type: 'Group', data: { serviceProviderId: selectedSrvId } }); setIsModalOpen(true); }}
                  onEdit={(item) => { setEditingItem({ type: 'Group', data: item }); setIsModalOpen(true); }}
                  onDelete={(item) => handleMutation('Group', 'delete', item)}
                  isAdmin={isAdmin}
                />
                <MasterColumn 
                  title="Group Members" 
                  icon={UserPlus} 
                  items={activeMembers.map(m => ({ ...m, name: m.userName }))} 
                  selectedId={null} 
                  disabled={!selectedGroupId}
                  onSelect={() => {}}
                  onAdd={() => { setEditingItem({ type: 'Group Member', data: { groupId: selectedGroupId } }); setIsModalOpen(true); }}
                  onEdit={(item) => { setEditingItem({ type: 'Group Member', data: item }); setIsModalOpen(true); }}
                  onDelete={(item) => handleMutation('Group Member', 'delete', item)}
                  isAdmin={isAdmin}
                />
              </div>
            </div>
          )}

          {activeTab === "audit" && (
            <div className="bg-white dark:bg-sn-sidebar rounded-[40px] border border-border dark:border-white/5 overflow-hidden shadow-2xl">
              <div className="p-8 border-b border-border dark:border-white/5 flex items-center justify-between bg-muted/20">
                <div className="flex items-center gap-3">
                  <History className="text-sn-green" />
                  <h2 className="text-2xl font-black">System Audit Trail</h2>
                </div>
                <div className="flex items-center gap-4">
                  <div className="px-4 py-2 bg-sn-dark rounded-xl text-white text-[10px] font-black uppercase tracking-widest">{auditLogs.length} Records</div>
                </div>
              </div>
              <div className="p-0 overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-muted/30">
                    <tr>
                      <th className="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Timestamp</th>
                      <th className="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Admin</th>
                      <th className="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Module</th>
                      <th className="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Action</th>
                      <th className="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Summary</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border dark:divide-white/5">
                    {auditLogs.map((log) => (
                      <tr key={log.id} className="hover:bg-muted/10 transition-colors">
                        <td className="px-8 py-5 text-xs font-bold text-muted-foreground">
                          {log.timestamp?.toDate ? format(log.timestamp.toDate(), "MMM d, HH:mm:ss") : "Just now"}
                        </td>
                        <td className="px-8 py-5">
                          <div className="flex items-center gap-2">
                            <div className="w-6 h-6 rounded-full bg-sn-green/20 flex items-center justify-center text-[10px] font-black text-sn-green">
                              {log.performedBy[0].toUpperCase()}
                            </div>
                            <div className="text-xs font-black">{log.performedBy}</div>
                          </div>
                        </td>
                        <td className="px-8 py-5">
                          <span className="px-3 py-1 bg-sn-dark text-white rounded-lg text-[9px] font-black uppercase">{log.moduleName}</span>
                        </td>
                        <td className="px-8 py-5">
                          <span className={cn(
                            "px-3 py-1 rounded-lg text-[9px] font-black uppercase",
                            log.action === 'create' ? "bg-green-500/10 text-green-500" :
                            log.action === 'update' ? "bg-blue-500/10 text-blue-500" : "bg-red-500/10 text-red-500"
                          )}>
                            {log.action}
                          </span>
                        </td>
                        <td className="px-8 py-5 text-xs font-medium text-muted-foreground">
                          {log.action === 'create' ? `Added "${log.newValue?.name || log.newValue?.userName}"` :
                           log.action === 'update' ? `Modified property of ID ${log.moduleId.slice(0,6)}` :
                           `Deactivated ID ${log.moduleId.slice(0,6)}`}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* ... existing automation/security tabs ... */}
        </motion.div>
      </AnimatePresence>

      {/* ── Notification Toast ── */}
      <AnimatePresence>
        {message && (
          <motion.div 
            initial={{ y: 100, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: 100, opacity: 0 }}
            className={cn(
              "fixed bottom-8 right-8 px-8 py-4 rounded-[20px] shadow-2xl flex items-center gap-3 z-50 backdrop-blur-xl border font-black text-sm",
              message.type === 'success' ? "bg-green-500/10 text-green-500 border-green-500/20" : "bg-red-500/10 text-red-500 border-red-500/20"
            )}
          >
            {message.type === 'success' ? <CheckCircle2 size={18} /> : <AlertCircle size={18} />}
            {message.text}
          </motion.div>
        )}
      </AnimatePresence>

      {/* ── Advanced Form Modal ── */}
      {isModalOpen && editingItem && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-sn-dark/80 backdrop-blur-md">
          <motion.div 
            initial={{ scale: 0.9, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            className="w-full max-w-lg bg-white dark:bg-sn-sidebar rounded-[40px] border border-border dark:border-white/10 shadow-2xl overflow-hidden"
          >
            <div className="p-8 border-b border-border dark:border-white/5 bg-muted/20">
              <h3 className="text-2xl font-black">{editingItem.data.id ? 'Edit' : 'Create'} {editingItem.type}</h3>
              <p className="text-muted-foreground text-sm font-medium mt-1">Configure global master data parameters.</p>
            </div>
            
            <form onSubmit={(e) => {
              e.preventDefault();
              const formData = new FormData(e.currentTarget);
              const data: any = {};
              formData.forEach((val, key) => data[key] = val);
              
              if (editingItem.type === 'Group Member') {
                const user = allUsers.find(u => u.id === data.userId);
                data.userName = user?.name || user?.email || "Unknown";
                data.userEmail = user?.email || "";
              }

              if (editingItem.data.id) {
                handleMutation(editingItem.type, 'update', { id: editingItem.data.id, old: editingItem.data, new: data });
              } else {
                handleMutation(editingItem.type, 'create', { ...editingItem.data, ...data });
              }
            }} className="p-8 space-y-6">
              
              {editingItem.type === 'Category' && (
                <>
                  <Input label="Category Name" name="name" defaultValue={editingItem.data.name} required />
                  <Textarea label="Description" name="description" defaultValue={editingItem.data.description} />
                </>
              )}

              {editingItem.type === 'Subcategory' && (
                <>
                  <Select label="Parent Category" name="categoryId" defaultValue={editingItem.data.categoryId} required>
                    {activeCategories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </Select>
                  <Input label="Sub-Category Name" name="name" defaultValue={editingItem.data.name} required />
                  <Textarea label="Description" name="description" defaultValue={editingItem.data.description} />
                </>
              )}

              {editingItem.type === 'Service Provider' && (
                <>
                  <Select label="Parent Sub-Category" name="subcategoryId" defaultValue={editingItem.data.subcategoryId} required>
                    {subcategories.filter(s => s.status === 'active').map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </Select>
                  <Input label="Provider Name" name="name" defaultValue={editingItem.data.name} required />
                  <Input label="SLA Commitment" name="sla" defaultValue={editingItem.data.sla} placeholder="e.g. 4h, 12h, 1d" required />
                  <Textarea label="Capability Details" name="description" defaultValue={editingItem.data.description} />
                </>
              )}

              {editingItem.type === 'Group' && (
                <>
                  <Select label="Parent Provider" name="serviceProviderId" defaultValue={editingItem.data.serviceProviderId} required>
                    {serviceProviders.filter(p => p.status === 'active').map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </Select>
                  <Input label="Group Name" name="name" defaultValue={editingItem.data.name} required />
                  <Input label="Shift Timing" name="shiftTiming" defaultValue={editingItem.data.shiftTiming} placeholder="e.g. 09:00 - 18:00" />
                  <Select label="Escalation Level" name="escalationLevel" defaultValue={editingItem.data.escalationLevel}>
                    <option value="L1">Level 1 - Frontline</option>
                    <option value="L2">Level 2 - Specialist</option>
                    <option value="L3">Level 3 - Expert</option>
                    <option value="Executive">Executive Escalation</option>
                  </Select>
                </>
              )}

              {editingItem.type === 'Group Member' && (
                <>
                  <Select label="Parent Group" name="groupId" defaultValue={editingItem.data.groupId} required>
                    {groups.filter(g => g.status === 'active').map(g => <option key={g.id} value={g.id}>{g.name}</option>)}
                  </Select>
                  <Select label="Select User" name="userId" defaultValue={editingItem.data.userId} required>
                    {allUsers.map(u => <option key={u.id} value={u.id}>{u.name || u.email}</option>)}
                  </Select>
                  <Select label="Group Role" name="roleInGroup" defaultValue={editingItem.data.roleInGroup} required>
                    <option value="agent">Standard Agent</option>
                    <option value="lead">Group Lead</option>
                    <option value="manager">Service Manager</option>
                  </Select>
                </>
              )}

              <div className="flex items-center gap-4 pt-4">
                <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)} className="flex-1 rounded-2xl py-6 font-black uppercase tracking-widest text-[10px]">Cancel</Button>
                <Button type="submit" disabled={loading} className="flex-1 bg-sn-green text-sn-dark rounded-2xl py-6 font-black uppercase tracking-widest text-[10px]">
                  {loading ? 'Processing...' : 'Confirm Changes'}
                </Button>
              </div>
            </form>
          </motion.div>
        </div>
      )}

    </div>
  );
}

function MasterColumn({ title, icon: Icon, items, selectedId, onSelect, onAdd, onEdit, onDelete, disabled, isAdmin }: any) {
  const [searchTerm, setSearchTerm] = useState("");
  
  const filteredItems = useMemo(() => {
    return items.filter((item: any) => 
      (item.name || "").toLowerCase().includes(searchTerm.toLowerCase()) ||
      (item.userName || "").toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [items, searchTerm]);

  return (
    <div className={cn(
      "flex flex-col bg-white/50 dark:bg-sn-sidebar/40 backdrop-blur-xl border border-border/50 dark:border-white/5 rounded-[40px] overflow-hidden shadow-sm transition-all duration-700",
      disabled ? "opacity-20 grayscale scale-[0.98] pointer-events-none" : "hover:shadow-2xl hover:border-sn-green/30"
    )}>
      <div className="p-6 border-b border-border/50 dark:border-white/5 bg-muted/20 space-y-4">
        <div className="flex items-center justify-between">
          <span className="font-black text-[11px] uppercase tracking-[0.2em] flex items-center gap-2">
            <Icon size={14} className="text-sn-green" /> {title}
          </span>
          <span className="text-[10px] font-black bg-sn-dark text-white px-3 py-1 rounded-full">{filteredItems.length}</span>
        </div>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={12} />
          <input 
            value={searchTerm}
            onChange={e => setSearchTerm(e.target.value)}
            placeholder={`Filter ${title.toLowerCase()}...`}
            className="w-full bg-white dark:bg-black/20 border border-border dark:border-white/5 rounded-xl pl-8 pr-4 py-2 text-[10px] font-bold outline-none focus:ring-1 focus:ring-sn-green/30 transition-all"
          />
        </div>
      </div>
      
      <div className="flex-1 overflow-y-auto p-4 space-y-2 custom-scrollbar">
        {filteredItems.map((item: any) => (
          <motion.div
            layout
            key={item.id}
            className={cn(
              "w-full flex items-center justify-between p-4 rounded-2xl group transition-all duration-500 border relative overflow-hidden",
              selectedId === item.id 
                ? "bg-sn-green/10 border-sn-green/30 shadow-inner" 
                : "hover:bg-muted/50 border-transparent"
            )}
          >
            <button 
              onClick={() => onSelect(item.id)}
              className="flex-1 text-left"
            >
              <div className={cn("text-sm font-black transition-colors", selectedId === item.id ? "text-sn-green" : "text-sn-dark dark:text-gray-200")}>
                {item.name}
              </div>
              {item.providerName && <div className="text-[9px] font-bold text-muted-foreground uppercase">{item.providerName}</div>}
              {item.sla && <div className="text-[9px] font-black text-sn-green uppercase">{item.sla} SLA</div>}
            </button>
            
            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              {isAdmin && (
                <>
                  <button onClick={() => onEdit(item)} className="p-2 text-sn-green hover:bg-sn-green/10 rounded-lg transition-all"><Edit3 size={14} /></button>
                  <button onClick={() => onDelete(item)} className="p-2 text-red-500 hover:bg-red-500/10 rounded-lg transition-all"><Trash2 size={14} /></button>
                </>
              )}
              <button className="p-2 text-muted-foreground hover:bg-muted rounded-lg transition-all"><ChevronRight size={14} /></button>
            </div>
          </motion.div>
        ))}
        {items.length === 0 && (
          <div className="flex flex-col items-center justify-center h-40 text-muted-foreground/30">
            <Icon size={32} strokeWidth={1} />
            <span className="text-[10px] font-black uppercase tracking-widest mt-2">
              {disabled ? `Select a parent to view ${title.toLowerCase()}` : `No ${title.toLowerCase()} found`}
            </span>
          </div>
        )}
      </div>

      {isAdmin && (
        <div className="p-5 border-t border-border/50 dark:border-white/5 bg-muted/10">
          <Button onClick={onAdd} className="w-full bg-sn-dark text-sn-green h-12 rounded-xl flex items-center justify-center gap-2 font-black text-[10px] uppercase tracking-widest hover:scale-[1.02] active:scale-[0.98] transition-all">
            <Plus size={16} /> New {title === "Categories" ? "Category" : title === "Sub-Categories" ? "Sub-Category" : title.endsWith('s') ? title.slice(0, -1) : title}
          </Button>
        </div>
      )}
    </div>
  );
}

function Input({ label, ...props }: any) {
  return (
    <div className="space-y-2">
      <label className="text-[10px] font-black uppercase tracking-widest text-muted-foreground ml-1">{label}</label>
      <input {...props} className="w-full bg-muted/50 dark:bg-black/20 border border-border dark:border-white/5 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-sn-green/30 transition-all" />
    </div>
  );
}

function Textarea({ label, ...props }: any) {
  return (
    <div className="space-y-2">
      <label className="text-[10px] font-black uppercase tracking-widest text-muted-foreground ml-1">{label}</label>
      <textarea {...props} className="w-full bg-muted/50 dark:bg-black/20 border border-border dark:border-white/5 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-sn-green/30 transition-all min-h-[100px]" />
    </div>
  );
}

function Select({ label, children, ...props }: any) {
  return (
    <div className="space-y-2">
      <label className="text-[10px] font-black uppercase tracking-widest text-muted-foreground ml-1">{label}</label>
      <select {...props} className="w-full bg-muted/50 dark:bg-black/20 border border-border dark:border-white/5 rounded-2xl px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-sn-green/30 transition-all appearance-none cursor-pointer">
        {children}
      </select>
    </div>
  );
}

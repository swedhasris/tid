import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { doc, onSnapshot, updateDoc, serverTimestamp, collection, addDoc, query, orderBy, getDocs } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { ChevronLeft, Send, History, MessageSquare, Save, Trash2, CheckCircle2, Clock, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { SLATimer } from "../components/SLATimer";
import { useServiceCatalog } from "../lib/serviceCatalog";

export function TicketDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user, profile } = useAuth();
  const { categories, subcategories, serviceProviders, groups } = useServiceCatalog();
  
  const [ticket, setTicket] = useState<any>(null);
  const [editedTicket, setEditedTicket] = useState<any>(null);
  const [comments, setComments] = useState<any[]>([]);
  const [newComment, setNewComment] = useState("");
  const [workNote, setWorkNote] = useState("");
  const [isUpdating, setIsUpdating] = useState(false);
  const [agents, setAgents] = useState<any[]>([]);

  const visibleCategories = categories.filter((item) => item.status === 'active');
  const visibleSubcategories = subcategories.filter(s => s.categoryId === editedTicket?.categoryId && s.status === 'active');
  const visibleProviders = serviceProviders.filter(p => p.subcategoryId === editedTicket?.subcategoryId && p.status === 'active');
  const visibleGroups = groups.filter(g => g.serviceProviderId === editedTicket?.serviceId && g.status === 'active');

  useEffect(() => {
    getDocs(collection(db, "users")).then(snap => {
      setAgents(snap.docs.map(d => ({ id: d.id, ...d.data() })).filter((u: any) => ["agent","admin","super_admin"].includes(u.role)));
    }).catch(() => {});
  }, []);

  useEffect(() => {
    if (!id) return;
    const unsubscribe = onSnapshot(doc(db, "tickets", id), (docSnapshot) => {
      if (docSnapshot.exists()) {
        const data = { id: docSnapshot.id, ...docSnapshot.data() };
        setTicket(data);
        setEditedTicket((prev: any) => prev ? prev : data);
      } else {
        navigate("/tickets");
      }
    }, (error) => {
      handleFirestoreError(error, OperationType.GET, `tickets/${id}`);
    });
    return unsubscribe;
  }, [id, navigate]);

  useEffect(() => {
    if (!id) return;
    const q = query(collection(db, "tickets", id, "comments"), orderBy("createdAt", "asc"));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      setComments(snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() })));
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, `tickets/${id}/comments`);
    });
    return unsubscribe;
  }, [id]);

  const handleUpdate = async () => {
    if (!id || !user || !editedTicket) return;
    setIsUpdating(true);
    try {
      const historyEntries: any[] = [];
      const fields = ["category", "categoryId", "subcategory", "subcategoryId", "service", "serviceId", "serviceProvider", "status", "impact", "urgency", "assignmentGroup", "title", "description", "assignedTo", "affectedUser"];
      
      fields.forEach(field => {
        if (editedTicket[field] !== ticket[field]) {
          historyEntries.push({
            action: `Field ${field} updated from ${ticket[field] || "none"} to ${editedTicket[field] || "none"}`,
            timestamp: new Date().toISOString(),
            user: profile?.name || user.email
          });
        }
      });

      if (historyEntries.length === 0) {
        setIsUpdating(false);
        return;
      }

      const { id: _, ...payload } = editedTicket;
      const updates: any = {
        ...payload,
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), ...historyEntries]
      };

      if (editedTicket.status !== ticket.status) {
        const isResolved = editedTicket.status === "Resolved" || editedTicket.status === "Closed";
        const isPaused = editedTicket.status === "On Hold" || editedTicket.status === "Waiting for Customer";
        
        if (isResolved && !ticket.resolvedAt) {
          updates.resolvedAt = new Date().toISOString();
          updates.resolutionSlaStatus = "Completed";
          updates.onHoldStart = null;
        } else if (!isResolved && ticket.resolvedAt) {
          updates.resolvedAt = null;
          updates.resolutionSlaStatus = "In Progress";
        }

        if (isPaused && !isResolved) {
          updates.onHoldStart = new Date().toISOString();
        } else if ((ticket.status === "On Hold" || ticket.status === "Waiting for Customer") && !isPaused) {
          const onHoldStartStr = ticket.onHoldStart || new Date().toISOString();
          const onHoldStart = new Date(onHoldStartStr).getTime();
          const now = new Date().getTime();
          const pauseDuration = Math.max(0, now - onHoldStart);
          
          const totalPaused = (Number(ticket.totalPausedTime) || 0) + pauseDuration;
          updates.totalPausedTime = totalPaused;
          updates.onHoldStart = null;

          if (ticket.resolutionDeadline) {
            const oldRes = new Date(ticket.resolutionDeadline).getTime();
            if (!isNaN(oldRes)) updates.resolutionDeadline = new Date(oldRes + pauseDuration).toISOString();
          }
          if (ticket.responseDeadline && !ticket.firstResponseAt) {
            const oldResp = new Date(ticket.responseDeadline).getTime();
            if (!isNaN(oldResp)) updates.responseDeadline = new Date(oldResp + pauseDuration).toISOString();
          }
        }
      }

      await updateDoc(doc(db, "tickets", id), updates);
      alert("Incident updated successfully");
    } catch (error: any) {
      console.error("Error updating ticket:", error);
      alert(`Failed to update incident: ${error.message || "Unknown error"}`);
    } finally {
      setIsUpdating(false);
    }
  };

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newComment.trim() || !id || !user) return;
    try {
      const now = new Date().toISOString();
      const historyEntry = { action: "Comment Added", timestamp: now, user: profile?.name || user.email };
      const updates: any = {
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), historyEntry]
      };
      if (!ticket.firstResponseAt) {
        updates.firstResponseAt = now;
        updates.responseSlaStatus = "Completed";
      }
      await addDoc(collection(db, "tickets", id, "comments"), {
        userId: user.uid,
        userName: profile?.name || user.email,
        message: newComment,
        type: "comment",
        createdAt: serverTimestamp()
      });
      await updateDoc(doc(db, "tickets", id), updates);
      setNewComment("");
    } catch (error) { console.error(error); }
  };

  const handleAddWorkNote = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!workNote.trim() || !id || !user) return;
    try {
      const historyEntry = { action: "Work Note Added", timestamp: new Date().toISOString(), user: profile?.name || user.email };
      await addDoc(collection(db, "tickets", id, "comments"), {
        userId: user.uid,
        userName: profile?.name || user.email,
        message: workNote,
        type: "work_note",
        createdAt: serverTimestamp()
      });
      await updateDoc(doc(db, "tickets", id), {
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), historyEntry]
      });
      setWorkNote("");
    } catch (error) { console.error(error); }
  };

  const updateLocalField = (field: string, value: string) => {
    setEditedTicket((prev: any) => ({ ...prev, [field]: value }));
  };

  const formatDate = (date: any) => {
    if (!date) return "-";
    if (typeof date.toDate === "function") return date.toDate().toLocaleString();
    if (typeof date === "string") return new Date(date).toLocaleString();
    if (date.seconds) return new Date(date.seconds * 1000).toLocaleString();
    return "-";
  };

  const [activeTab, setActiveTab] = useState("Notes");

  if (!ticket) return null;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between bg-white p-3 border border-border rounded-lg shadow-sm">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => navigate("/tickets")} className="gap-2 h-8 px-2">
            <ChevronLeft className="w-4 h-4" />
          </Button>
          <div className="flex flex-col">
            <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider leading-none mb-1">Incident</span>
            <span className="text-sm font-bold leading-none">{ticket.number}</span>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={handleUpdate} disabled={isUpdating} className="h-8 px-4 font-bold border-border bg-white text-sn-dark">Update</Button>
            <Button size="sm" onClick={handleUpdate} disabled={isUpdating} className="h-8 px-4 font-bold bg-sn-green text-sn-dark shadow-sm hover:bg-sn-green/90">Submit</Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div className="lg:col-span-3 space-y-6">
          <div className="bg-white border border-border rounded-lg shadow-sm p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
              <div className="space-y-4">
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Number</label>
                  <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" value={ticket.number} />
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Reporting User</label>
                  <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs" value={ticket.caller || ''} />
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Category</label>
                  <select 
                    value={editedTicket?.categoryId || ""}
                    onChange={(e) => {
                      const category = visibleCategories.find((item) => item.id === e.target.value);
                      setEditedTicket((prev: any) => ({ ...prev, categoryId: e.target.value, category: category?.name || "", subcategoryId: "", subcategory: "", serviceId: "", service: "", serviceProvider: "", assignmentGroup: "" }));
                    }}
                    className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                  >
                    {visibleCategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Subcategory</label>
                  <select 
                    value={editedTicket?.subcategoryId || ""}
                    onChange={(e) => {
                      const subcategory = visibleSubcategories.find((item) => item.id === e.target.value);
                      setEditedTicket((prev: any) => ({ ...prev, subcategoryId: e.target.value, subcategory: subcategory?.name || "", serviceId: "", service: "", serviceProvider: "", assignmentGroup: "" }));
                    }}
                    className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                  >
                    <option value="">-- None --</option>
                    {visibleSubcategories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Service Provider</label>
                  <select 
                    value={editedTicket?.serviceId || ""} 
                    onChange={(e) => {
                      const service = visibleProviders.find((item) => item.id === e.target.value);
                      setEditedTicket((prev: any) => ({ ...prev, serviceId: e.target.value, service: service?.name || "", serviceProvider: service?.name || "", assignmentGroup: "" }));
                    }}
                    className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8"
                  >
                    <option value="">-- Select Service --</option>
                    {visibleProviders.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                  </select>
                </div>
              </div>

              <div className="space-y-4">
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">State</label>
                  <select value={editedTicket?.status || ""} onChange={(e) => updateLocalField("status", e.target.value)} className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8">
                    {["New", "In Progress", "On Hold", "Resolved", "Closed", "Canceled"].map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Priority</label>
                  <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-bold text-blue-600 h-8" value={editedTicket?.priority || ""} />
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assignment group</label>
                  <select className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8" value={editedTicket?.assignmentGroup || ""} onChange={(e) => updateLocalField("assignmentGroup", e.target.value)}>
                    <option value="">-- None --</option>
                    {visibleGroups.map((item) => <option key={item.id} value={item.name}>{item.name}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assigned to</label>
                  <select className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8" value={editedTicket?.assignedTo || ""} onChange={(e) => updateLocalField("assignedTo", e.target.value)}>
                    <option value="">-- None --</option>
                    {agents.map(agent => <option key={agent.id} value={agent.id}>{agent.name || agent.email}</option>)}
                  </select>
                </div>
              </div>

              <div className="col-span-1 md:col-span-2 mt-4 space-y-4">
                <div className="grid grid-cols-6 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Short description</label>
                  <input className="col-span-5 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" value={editedTicket?.title || ""} onChange={(e) => updateLocalField("title", e.target.value)} />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="bg-white border border-border rounded-lg shadow-sm overflow-hidden p-4">
          <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-4">Activity Stream</h3>
          <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2">
            {comments.map((comment) => (
              <div key={comment.id} className="border-l-2 border-sn-green pl-3 py-1">
                <div className="flex justify-between text-[10px] text-muted-foreground mb-1">
                  <span className="font-bold">{comment.userName}</span>
                  <span>{formatDate(comment.createdAt)}</span>
                </div>
                <p className="text-xs italic">{comment.message}</p>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="bg-white border border-border rounded-lg shadow-sm p-6">
        <label className="text-[11px] font-bold text-muted-foreground uppercase tracking-tight mb-2 block">Additional Comments (Public)</label>
        <form onSubmit={handleAddComment} className="space-y-2">
          <textarea value={newComment} onChange={(e) => setNewComment(e.target.value)} placeholder="Type public messages here..." className="w-full p-3 border border-border rounded text-xs outline-none min-h-[100px] resize-none" />
          <div className="flex justify-end"><Button type="submit" size="sm" className="bg-sn-green text-sn-dark font-bold h-8">Post Comment</Button></div>
        </form>
      </div>
    </div>
  );
}

import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { doc, onSnapshot, updateDoc, serverTimestamp, collection, addDoc, query, orderBy } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { ChevronLeft, Send, History, MessageSquare, Save, Trash2, CheckCircle2, Clock, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { SLATimer } from "../components/SLATimer";

export function TicketDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user, profile } = useAuth();
  const [ticket, setTicket] = useState<any>(null);
  const [editedTicket, setEditedTicket] = useState<any>(null);
  const [comments, setComments] = useState<any[]>([]);
  const [newComment, setNewComment] = useState("");
  const [isUpdating, setIsUpdating] = useState(false);

  useEffect(() => {
    if (!id) return;
    const unsubscribe = onSnapshot(doc(db, "tickets", id), (doc) => {
      if (doc.exists()) {
        const data = { id: doc.id, ...doc.data() };
        setTicket(data);
        // Only initialize editedTicket if it's the first load or if we want to sync with remote
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
      const fields = ["category", "subcategory", "service", "status", "impact", "urgency", "assignmentGroup", "title", "description", "assignedTo"];
      
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

      // Handle SLA Pause/Resume/Complete
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
          // Resume: Calculate how long it was paused
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

      console.log("Sending updates to Firestore:", updates);
      await updateDoc(doc(db, "tickets", id), updates);
      console.log("Firestore update successful.");
      alert("Incident updated successfully");
    } catch (error: any) {
      console.error("CRITICAL: Error updating ticket:", error);
      alert(`Failed to update incident: ${error.message || "Unknown error"}`);
    } finally {
      setIsUpdating(false);
    }
  };

  const handleAddComment = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newComment.trim() || !id || !user) return;

    try {
      const historyEntry = { 
        action: "Comment Added", 
        timestamp: new Date().toISOString(), 
        user: profile?.name || user.email 
      };

      const updates: any = {
        updatedAt: serverTimestamp(),
        history: [...(ticket.history || []), historyEntry]
      };

      if (!ticket.firstResponseAt && (profile?.role === "agent" || profile?.role === "admin")) {
        updates.firstResponseAt = new Date().toISOString();
        updates.responseSlaStatus = "Completed";
      }

      await addDoc(collection(db, "tickets", id, "comments"), {
        userId: user.uid,
        userName: profile?.name || user.email,
        message: newComment,
        createdAt: serverTimestamp()
      });

      await updateDoc(doc(db, "tickets", id), updates);
      setNewComment("");
    } catch (error) {
      console.error("Error adding comment:", error);
    }
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

  const [activeTab, setActiveTab] = useState("Activity");

  const handleDeleteTicket = async () => {
    if (!id || !window.confirm("Are you sure you want to delete this incident?")) return;
    try {
      const response = await fetch(`/api/tickets/${id}`, { method: "DELETE" });
      if (response.ok) {
        navigate("/tickets");
      } else {
        const data = await response.json();
        alert(data.error || "Failed to delete ticket");
      }
    } catch (error) {
      console.error("Error deleting ticket:", error);
    }
  };

  const handleResolveTicket = async () => {
    if (!id || !editedTicket) return;
    setEditedTicket({ ...editedTicket, status: "Resolved" });
    // We can either let the user click Update or trigger it automatically
    // Connect usually requires resolution notes, but for now we'll just set the status
    alert("Status set to Resolved. Please click Update to save changes.");
  };

  const handleCreateTicketFromDetail = async () => {
    // This is for the 'Submit' button in the top bar if we were creating a NEW record
    // But since we are in TicketDetail, normally it's 'Update'.
    // However, if the user requested 'Submit', I'll make it alias to handleUpdate.
    handleUpdate();
  };

  if (!ticket) return null;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Top Action Bar */}
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
          <div className="flex items-center gap-6 mr-6 border-r border-border pr-6">
             <SLATimer 
              label="Response" 
              startTime={ticket.createdAt} 
              deadline={ticket.responseDeadline}
              stoppedAt={ticket.firstResponseAt}
              onHoldStart={ticket.status === "On Hold" ? ticket.onHoldStart : null}
              totalPausedTime={ticket.totalPausedTime}
            />
            <SLATimer 
              label="Resolution" 
              startTime={ticket.createdAt} 
              deadline={ticket.resolutionDeadline}
              stoppedAt={ticket.resolvedAt}
              onHoldStart={ticket.status === "On Hold" ? ticket.onHoldStart : null}
              totalPausedTime={ticket.totalPausedTime}
            />
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" className="h-8 px-4 font-bold border-border bg-white text-sn-dark">Follow</Button>
            <Button 
              variant="outline" 
              size="sm" 
              onClick={handleUpdate} 
              disabled={isUpdating}
              className="h-8 px-4 font-bold border-border bg-white text-sn-dark disabled:opacity-50"
            >
              {isUpdating ? "Saving..." : "Update"}
            </Button>
            {ticket.status !== "Resolved" && (
              <Button variant="outline" size="sm" onClick={handleResolveTicket} className="h-8 px-4 font-bold border-border bg-white text-sn-dark">Resolve</Button>
            )}
            <Button 
              size="sm" 
              onClick={handleUpdate}
              disabled={isUpdating}
              className="h-8 px-4 font-bold bg-sn-green text-sn-dark shadow-sm hover:bg-sn-green/90 disabled:opacity-50"
            >
              {isUpdating ? "Saving..." : "Submit"}
            </Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Main Form Area */}
        <div className="lg:col-span-3 space-y-6">
          <div className="bg-white border border-border rounded-lg shadow-sm p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
              {/* Left Column */}
              <div className="space-y-4">
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Number</label>
                  <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" value={ticket.number} />
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Caller</label>
                  <div className="col-span-2 flex gap-1">
                    <input readOnly className="flex-grow p-1.5 bg-muted/30 border border-border rounded text-xs" value={ticket.caller} />
                    <Button variant="outline" size="icon" className="w-8 h-8 flex-shrink-0"><span className="text-[10px]">i</span></Button>
                  </div>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Category</label>
                  <select 
                    value={editedTicket?.category || ""}
                    onChange={(e) => updateLocalField("category", e.target.value)}
                    className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                  >
                    {["Inquiry / Help", "Software", "Hardware", "Network", "Database"].map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Subcategory</label>
                  <select 
                    value={editedTicket?.subcategory || ""}
                    onChange={(e) => updateLocalField("subcategory", e.target.value)}
                    className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                  >
                    <option value="">-- None --</option>
                    {["Antivirus", "Email", "Operating System", "Internal Application"].map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Service</label>
                  <input className="col-span-2 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" value={editedTicket?.service || ""} onChange={(e) => updateLocalField("service", e.target.value)} />
                </div>
              </div>

              {/* Right Column */}
              <div className="space-y-4">
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">State</label>
                  <select 
                    value={editedTicket?.status || ""}
                    onChange={(e) => updateLocalField("status", e.target.value)}
                    className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                  >
                    {["New", "In Progress", "On Hold", "Resolved", "Closed", "Canceled"].map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Impact</label>
                  <select 
                    value={editedTicket?.impact || ""}
                    onChange={(e) => updateLocalField("impact", e.target.value)}
                    className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                  >
                    {["1 - High", "2 - Medium", "3 - Low"].map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Urgency</label>
                  <select 
                    value={editedTicket?.urgency || ""}
                    onChange={(e) => updateLocalField("urgency", e.target.value)}
                    className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                  >
                    {["1 - High", "2 - Medium", "3 - Low"].map(s => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Priority</label>
                  <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-bold text-blue-600 h-8" value={editedTicket?.priority || ""} />
                </div>
                <div className="grid grid-cols-3 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assignment group</label>
                  <input className="col-span-2 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" value={editedTicket?.assignmentGroup || ""} onChange={(e) => updateLocalField("assignmentGroup", e.target.value)} />
                </div>
              </div>

              {/* Full Width */}
              <div className="col-span-1 md:col-span-2 mt-4 space-y-4">
                <div className="grid grid-cols-6 items-center gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Short description</label>
                  <input className="col-span-5 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" value={editedTicket?.title || ""} onChange={(e) => updateLocalField("title", e.target.value)} />
                </div>
                <div className="grid grid-cols-6 items-start gap-4">
                  <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight mt-2">Description</label>
                  <textarea rows={3} className="col-span-5 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8 resize-none h-24" value={editedTicket?.description || ""} onChange={(e) => updateLocalField("description", e.target.value)} />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Sidebar: Related Search */}
        <div className="space-y-4">
          <div className="bg-white border border-border rounded-lg shadow-sm overflow-hidden">
            <div className="p-3 border-b border-border bg-muted/10 font-bold text-[11px] uppercase tracking-wider">Related Search Results</div>
            <div className="p-4 space-y-4">
              <div className="text-[11px] text-muted-foreground italic">Searching knowledge base for: "{ticket.title}"</div>
              <div className="space-y-2">
                <div className="p-2 border border-border rounded hover:bg-muted/10 cursor-pointer">
                  <div className="text-[11px] font-bold text-blue-600 line-clamp-1">How to reset network settings</div>
                  <div className="text-[10px] text-muted-foreground line-clamp-2 mt-1">Generic steps to reset your DNS and IP settings on Windows...</div>
                </div>
                <div className="p-2 border border-border rounded hover:bg-muted/10 cursor-pointer">
                  <div className="text-[11px] font-bold text-blue-600 line-clamp-1">Troubleshooting slow connection</div>
                  <div className="text-[10px] text-muted-foreground line-clamp-2 mt-1">Check these common points of failure for persistent networking issues...</div>
                </div>
              </div>
              <Button variant="ghost" size="sm" className="w-full text-[10px] font-bold uppercase tracking-tight text-muted-foreground h-8">View All Results</Button>
            </div>
          </div>
        </div>
      </div>

      {/* Bottom Tabs Section */}
      <div className="bg-white border border-border rounded-lg shadow-sm overflow-hidden min-h-[400px]">
        <div className="flex border-b border-border bg-muted/10">
          {["Notes", "Related Records", "Resolution Information"].map(tab => (
            <button 
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={cn(
                "px-6 py-3 text-[11px] font-bold uppercase tracking-wider transition-all border-r border-border",
                activeTab === tab ? "bg-white border-b-2 border-b-sn-green -mb-[1px]" : "text-muted-foreground hover:bg-muted/30"
              )}
            >
              {tab}
            </button>
          ))}
        </div>

        <div className="p-6">
          {activeTab === "Notes" && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div className="space-y-6">
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Watch list</label>
                    <div className="col-span-2 flex gap-1">
                       <div className="flex-grow p-1.5 border border-border rounded text-xs bg-muted/20 min-h-[32px] flex items-center">
                         <span className="text-muted-foreground italic">Add users to watch list...</span>
                       </div>
                       <Button variant="outline" size="sm" className="h-8 w-8 p-0"><Plus className="w-3 h-3" /></Button>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Work notes list</label>
                    <div className="col-span-2 flex gap-1">
                       <div className="flex-grow p-1.5 border border-border rounded text-xs bg-muted/20 min-h-[32px] flex items-center">
                         <span className="text-muted-foreground italic">Add team members...</span>
                       </div>
                       <Button variant="outline" size="sm" className="h-8 w-8 p-0"><Plus className="w-3 h-3" /></Button>
                    </div>
                  </div>
                </div>

                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <label className="text-[11px] font-bold text-muted-foreground uppercase tracking-tight">Work Notes (Private)</label>
                    <div className="flex items-center gap-2">
                      <input type="checkbox" id="workNotesCheck" className="w-3 h-3 h-8 accent-sn-green" />
                      <label htmlFor="workNotesCheck" className="text-[10px] text-muted-foreground">Internal only</label>
                    </div>
                  </div>
                  <textarea 
                    placeholder="Type internal work notes here..." 
                    className="w-full p-3 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none min-h-[100px] resize-none bg-yellow-50/20"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-[11px] font-bold text-muted-foreground uppercase tracking-tight">Additional Comments (Customer visible)</label>
                  <form onSubmit={handleAddComment} className="space-y-2">
                    <textarea 
                      value={newComment}
                      onChange={(e) => setNewComment(e.target.value)}
                      placeholder="Type public messages here..." 
                      className="w-full p-3 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none min-h-[100px] resize-none"
                    />
                    <div className="flex justify-end">
                       <Button type="submit" size="sm" className="bg-sn-green text-sn-dark font-bold gap-2 h-8">
                         <Send className="w-3 h-3" /> Post Comment
                       </Button>
                    </div>
                  </form>
                </div>
              </div>

              <div className="space-y-4">
                <div className="flex items-center justify-between border-b border-border pb-2">
                   <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Activity Stream</h3>
                   <Button variant="ghost" size="sm" className="h-6 text-[10px] font-bold uppercase p-0 px-2">Filter</Button>
                </div>
                <div className="space-y-6 max-h-[600px] overflow-y-auto pr-2">
                  {comments.map((comment) => (
                    <div key={comment.id} className="relative pl-8 pb-6 border-l border-border last:border-0 ml-2">
                      <div className="absolute -left-2 top-0 w-4 h-4 rounded-full bg-sn-green flex items-center justify-center">
                        <MessageSquare className="w-2.5 h-2.5 text-sn-dark" />
                      </div>
                      <div className="bg-muted/10 border border-border rounded p-3">
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-[11px] font-bold">{comment.userName}</span>
                          <span className="text-[10px] text-muted-foreground">{formatDate(comment.createdAt)}</span>
                        </div>
                        <p className="text-[11px] text-foreground leading-relaxed italic">{comment.message}</p>
                      </div>
                    </div>
                  ))}
                  
                  {ticket.history?.slice().reverse().map((item: any, idx: number) => (
                    <div key={idx} className="relative pl-8 pb-6 border-l border-border last:border-0 ml-2">
                       <div className="absolute -left-2 top-0 w-4 h-4 rounded-full bg-muted flex items-center justify-center border border-border">
                        <History className="w-2.5 h-2.5 text-muted-foreground" />
                      </div>
                      <div className="py-1">
                        <div className="text-[10px] text-muted-foreground font-bold uppercase">{item.action}</div>
                        <div className="text-[9px] text-muted-foreground">{new Date(item.timestamp).toLocaleString()} by {item.user}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {activeTab === "Related Records" && (
            <div className="space-y-8">
              {/* Task SLAs Subtab (Nested like video) */}
              <div className="space-y-4">
                <div className="flex items-center gap-4 border-b border-border">
                  <button className="px-4 py-2 text-[11px] font-bold uppercase border-b-2 border-sn-green -mb-[2px]">Task SLAs</button>
                  <button className="px-4 py-2 text-[11px] font-bold uppercase text-muted-foreground hover:bg-muted/30">Affected CIs</button>
                  <button className="px-4 py-2 text-[11px] font-bold uppercase text-muted-foreground hover:bg-muted/30">Impacted Services/CIs</button>
                  <button className="px-4 py-2 text-[11px] font-bold uppercase text-muted-foreground hover:bg-muted/30">Child Incidents</button>
                </div>
                
                <div className="bg-white border border-border rounded overflow-hidden">
                  <table className="w-full text-left border-collapse">
                    <thead className="bg-muted/30">
                      <tr className="border-b border-border">
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">SLA Definition</th>
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Target</th>
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Stage</th>
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Business time left</th>
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Actual elapsed percentage</th>
                        <th className="p-2 text-[10px] font-bold uppercase text-muted-foreground tracking-tight">Start time</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr className="border-b border-border hover:bg-muted/5">
                        <td className="p-2 text-xs font-bold text-blue-600">Response SLA</td>
                        <td className="p-2 text-[11px]">Response</td>
                        <td className="p-2">
                           <span className={cn(
                             "px-1.5 py-0.5 rounded text-[10px] font-bold uppercase",
                             ticket.firstResponseAt ? "bg-green-100 text-green-700" : "bg-blue-100 text-blue-700"
                           )}>
                             {ticket.firstResponseAt ? "Completed" : "In Progress"}
                           </span>
                        </td>
                        <td className="p-2 text-[11px] font-mono">{ticket.firstResponseAt ? "-" : "0h 45m"}</td>
                        <td className="p-2 text-[11px]">{ticket.firstResponseAt ? "10%" : "25%"}</td>
                        <td className="p-2 text-[11px]">{formatDate(ticket.createdAt)}</td>
                      </tr>
                      <tr className="border-b border-border hover:bg-muted/5">
                        <td className="p-2 text-xs font-bold text-blue-600">Resolution SLA</td>
                        <td className="p-2 text-[11px]">Resolution</td>
                        <td className="p-2">
                           <span className={cn(
                             "px-1.5 py-0.5 rounded text-[10px] font-bold uppercase",
                             ticket.resolvedAt ? "bg-green-100 text-green-700" : "bg-blue-100 text-blue-700"
                           )}>
                             {ticket.resolvedAt ? "Completed" : "In Progress"}
                           </span>
                        </td>
                        <td className="p-2 text-[11px] font-mono">{ticket.resolvedAt ? "-" : "4h 20m"}</td>
                        <td className="p-2 text-[11px]">{ticket.resolvedAt ? "45%" : "12%"}</td>
                        <td className="p-2 text-[11px]">{formatDate(ticket.createdAt)}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}

          {activeTab === "Resolution Information" && (
            <div className="max-w-3xl space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Resolution code</label>
                    <select className="col-span-2 p-1.5 border border-border rounded text-xs outline-none h-8">
                      <option>-- None --</option>
                      <option>Solved (Permanently)</option>
                      <option>Solved (Workaround)</option>
                      <option>Closed/Resolved by Caller</option>
                      <option>Duplicate Incident</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Resolved by</label>
                    <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs h-8" value={profile?.name || ""} />
                  </div>
                </div>
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Resolved</label>
                    <input readOnly className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs h-8" value={ticket.resolvedAt || ""} />
                  </div>
                </div>
              </div>
              <div className="grid grid-cols-6 items-start gap-4">
                <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight mt-2">Resolution notes</label>
                <textarea rows={4} className="col-span-5 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green resize-none h-32" />
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

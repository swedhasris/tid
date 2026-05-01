import React, { useEffect, useState } from "react";
import { collection, addDoc, query, onSnapshot, updateDoc, doc, serverTimestamp, orderBy, where } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Plus, Filter, MoreVertical, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

import { Link, useSearchParams } from "react-router-dom";

function SLATimer({ deadline, metAt, isPaused, onHoldStart, totalPausedTime = 0, label }: { deadline: string, metAt?: string, isPaused?: boolean, onHoldStart?: string, totalPausedTime?: number, label: string }) {
  const [displayTime, setDisplayTime] = useState("");
  const [status, setStatus] = useState<"met" | "breached" | "active" | "paused">("active");

  useEffect(() => {
    if (metAt) {
      setStatus("met");
      setDisplayTime("MET");
      return;
    }

    const timer = setInterval(() => {
      const now = Date.now();
      const targetTime = new Date(deadline).getTime();
      const currentNow = isPaused && onHoldStart ? new Date(onHoldStart).getTime() : now;
      
      const diff = (targetTime + totalPausedTime) - currentNow;

      if (diff <= 0) {
        setStatus("breached");
        setDisplayTime("BREACHED");
      } else {
        if (isPaused) {
          setStatus("paused");
        } else {
          setStatus("active");
        }
        
        const absDiff = Math.abs(diff);
        const h = Math.floor(absDiff / (1000 * 60 * 60));
        const m = Math.floor((absDiff % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((absDiff % (1000 * 60)) / 1000);
        
        const formatted = `${h.toString().padStart(2, "0")}:${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
        setDisplayTime(formatted);
      }
    }, 1000);

    return () => clearInterval(timer);
  }, [deadline, metAt, isPaused, onHoldStart, totalPausedTime]);

  return (
    <div className="flex flex-col gap-0.5 min-w-[80px]">
      <span className="text-[9px] uppercase text-muted-foreground font-bold leading-none">{label}</span>
      <span className={cn(
        "text-[11px] font-mono font-bold leading-none",
        status === "met" ? "text-green-600" :
        status === "breached" ? "text-red-600" :
        status === "paused" ? "text-orange-500" : "text-blue-600"
      )}>
        {displayTime}
      </span>
    </div>
  );
}

export function Tickets() {
  const { user, profile } = useAuth();
  const [searchParams] = useSearchParams();
  const filter = searchParams.get("filter");
  const action = searchParams.get("action");

  const [tickets, setTickets] = useState<any[]>([]);
  const [agents, setAgents] = useState<any[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (action === "new") {
      setIsModalOpen(true);
    }
  }, [action]);

  const [newTicket, setNewTicket] = useState({ 
    caller: "",
    category: "Inquiry / Help",
    subcategory: "",
    service: "",
    serviceOffering: "",
    cmdbItem: "",
    title: "", 
    description: "", 
    channel: "Self-service",
    impact: "2 - Medium",
    urgency: "2 - Medium",
    assignmentGroup: "",
    assignedTo: ""
  });

  const [assignedTo, setAssignedTo] = useState("");
  const [slaPolicies, setSlaPolicies] = useState<any[]>([]);

  useEffect(() => {
    const q = query(collection(db, "sla_policies"), where("isActive", "==", true));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      setSlaPolicies(snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() })));
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, "sla_policies");
    });
    return unsubscribe;
  }, []);

  useEffect(() => {
    if (!user || !profile) return;

    const ticketsRef = collection(db, "tickets");
    const isAgent = profile?.role === "agent" || profile?.role === "admin" || profile?.role === "super_admin";
    
    let q = query(ticketsRef, orderBy("createdAt", "desc"));
    
    // If not agent, only see created by me (enforced by rules too)
    if (!isAgent) {
      q = query(ticketsRef, where("createdBy", "==", user.uid), orderBy("createdAt", "desc"));
    }

    const unsubscribe = onSnapshot(q, (snapshot) => {
      const ticketsData = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      setTickets(ticketsData);
    }, (error) => {
      console.error("Firestore Error in Tickets List:", error);
      // We don't throw here to avoid crashing the UI, but we log the error
    });

    return unsubscribe;
  }, [user, profile]);

  useEffect(() => {
    const q = query(collection(db, "users"));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      const allUsers = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      setAgents(allUsers.filter((u: any) => u.role === "agent" || u.role === "admin"));
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, "users");
    });
    return unsubscribe;
  }, []);

  const formatDate = (date: any) => {
    if (!date) return "-";
    if (typeof date.toDate === "function") {
      return date.toDate().toLocaleDateString();
    }
    if (typeof date === "string") {
      return new Date(date).toLocaleDateString();
    }
    if (date.seconds) {
      return new Date(date.seconds * 1000).toLocaleDateString();
    }
    return "-";
  };

  const formatDateTime = (date: any) => {
    if (!date) return "-";
    if (typeof date.toDate === "function") {
      return date.toDate().toISOString();
    }
    if (typeof date === "string") {
      return date;
    }
    if (date.seconds) {
      return new Date(date.seconds * 1000).toISOString();
    }
    return undefined;
  };

  const [columnFilters, setColumnFilters] = useState({
    number: "",
    title: "",
    caller: "",
    priority: "",
    status: "",
    category: "",
    assignmentGroup: "",
    assignedTo: ""
  });

  const filteredTickets = tickets.filter(t => {
    // Top-level quick filters
    if (filter === "assigned_to_me" && t.assignedTo !== user?.uid) return false;
    if (filter === "open" && (t.status === "Resolved" || t.status === "Closed")) return false;
    if (filter === "unassigned" && t.assignedTo) return false;
    if (filter === "resolved" && t.status !== "Resolved" && t.status !== "Closed") return false;

    // Column-level search filters (case-insensitive)
    const matches = (val: string, filterVal: string) => !filterVal || (val || "").toLowerCase().includes(filterVal.toLowerCase());
    
    return (
      matches(t.number, columnFilters.number) &&
      matches(t.title, columnFilters.title) &&
      matches(t.caller, columnFilters.caller) &&
      matches(t.priority, columnFilters.priority) &&
      matches(t.status, columnFilters.status) &&
      matches(t.category, columnFilters.category) &&
      matches(t.assignmentGroup, columnFilters.assignmentGroup) &&
      matches(agents.find(a => a.id === t.assignedTo)?.name || "", columnFilters.assignedTo)
    );
  });

  const calculatePriority = (impact: string, urgency: string) => {
    const i = parseInt(impact[0]);
    const u = parseInt(urgency[0]);
    const sum = i + u;
    if (sum <= 2) return "1 - Critical";
    if (sum === 3) return "2 - High";
    if (sum === 4) return "3 - Moderate";
    return "4 - Low";
  };

  const handleCreateTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isSubmitting) return;
    
    if (!user) {
      alert("You must be logged in to create a ticket.");
      return;
    }

    // Required fields check
    if (!newTicket.caller || !newTicket.title) {
      alert("Please fill in all required fields (Caller and Short description).");
      return;
    }

    setIsSubmitting(true);
    console.log("Submitting new ticket:", newTicket);

    try {
      let priority = calculatePriority(newTicket.impact, newTicket.urgency);
      
      // Find matching SLA policy
      const matchingPolicy = slaPolicies.find(p => p.priority === priority && (p.category === newTicket.category || !p.category)) 
        || slaPolicies.find(p => p.priority === priority)
        || { responseTimeHours: 4, resolutionTimeHours: 24 }; // Fallback

      const now = Date.now();
      const responseDeadline = new Date(now + (matchingPolicy.responseTimeHours || 4) * 60 * 60 * 1000);
      const resolutionDeadline = new Date(now + (matchingPolicy.resolutionTimeHours || 24) * 60 * 60 * 1000);

      const ticketNumber = `INC${Math.floor(1000000 + Math.random() * 9000000)}`;

      // Immediate Breach Check (SLA Engine simulation for creation)
      let responseSlaStatus = "In Progress";
      let resolutionSlaStatus = "In Progress";
      
      if (responseDeadline.getTime() <= now || resolutionDeadline.getTime() <= now) {
        priority = "1 - Critical";
        responseSlaStatus = responseDeadline.getTime() <= now ? "Breached" : "In Progress";
        resolutionSlaStatus = resolutionDeadline.getTime() <= now ? "Breached" : "In Progress";
      }

      // Workflow Automation: Auto-assignment based on category
      let assignmentGroup = newTicket.assignmentGroup;
      if (!assignmentGroup) {
        switch (newTicket.category) {
          case "Network": assignmentGroup = "Network Team"; break;
          case "Hardware": assignmentGroup = "Hardware Support"; break;
          case "Software": assignmentGroup = "App Support"; break;
          case "Database": assignmentGroup = "DBA Team"; break;
          default: assignmentGroup = "Service Desk";
        }
      }

      const ticketData = {
        ...newTicket,
        number: ticketNumber,
        assignmentGroup,
        priority,
        status: "New",
        createdBy: user.uid,
        createdAt: serverTimestamp(),
        updatedAt: serverTimestamp(),
        responseDeadline: responseDeadline.toISOString(),
        resolutionDeadline: resolutionDeadline.toISOString(),
        responseSlaStatus,
        resolutionSlaStatus,
        totalPausedTime: 0,
        history: [{ action: "Ticket Created", timestamp: new Date().toISOString(), user: profile?.name || user.email }]
      };

      console.log("Final ticket data payload:", ticketData);

      const docRef = await addDoc(collection(db, "tickets"), ticketData);
      console.log("Ticket created successfully with ID:", docRef.id);

      setIsModalOpen(false);
      alert(`Ticket ${ticketNumber} has been created successfully.`);
      
      setNewTicket({ 
        caller: "",
        category: "Inquiry / Help",
        subcategory: "",
        service: "",
        serviceOffering: "",
        cmdbItem: "",
        title: "", 
        description: "", 
        channel: "Self-service",
        impact: "2 - Medium",
        urgency: "2 - Medium",
        assignmentGroup: "",
        assignedTo: ""
      });
    } catch (error: any) {
      console.error("CRITICAL: Error creating ticket:", error);
      alert(`Failed to create ticket: ${error.message || "Unknown error"}. Please check your connection and try again.`);
    } finally {
      setIsSubmitting(false);
    }
  };

  const updateStatus = async (ticketId: string, newStatus: string) => {
    const ticketRef = doc(db, "tickets", ticketId);
    const ticket = tickets.find(t => t.id === ticketId);
    await updateDoc(ticketRef, {
      status: newStatus,
      updatedAt: serverTimestamp(),
      history: [
        ...(ticket?.history || []),
        { action: `Status updated to ${newStatus}`, timestamp: new Date().toISOString(), user: profile?.name || user?.email }
      ]
    });
  };

  const updateAssignment = async (ticketId: string, agentId: string) => {
    const ticketRef = doc(db, "tickets", ticketId);
    const ticket = tickets.find(t => t.id === ticketId);
    const agent = agents.find(a => a.id === agentId);
    await updateDoc(ticketRef, {
      assignedTo: agentId,
      status: agentId ? "Assigned" : "New",
      updatedAt: serverTimestamp(),
      history: [
        ...(ticket?.history || []),
        { action: `Assigned to ${agent?.name || "None"}`, timestamp: new Date().toISOString(), user: profile?.name || user?.email }
      ]
    });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Tickets</h1>
          <p className="text-muted-foreground">Manage and track IT support requests.</p>
        </div>
        <Button onClick={() => setIsModalOpen(true)} className="bg-sn-green text-sn-dark font-bold">
          <Plus className="w-4 h-4 mr-2" /> Create Ticket
        </Button>
      </div>

      <div className="sn-card overflow-hidden p-0">
        <div className="p-4 border-b border-border flex items-center justify-between bg-muted/30">
          <div className="flex items-center gap-4">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input 
                type="text" 
                placeholder="Search..." 
                className="pl-9 pr-4 py-2 bg-white border border-border rounded-md text-sm w-64 focus:ring-2 focus:ring-sn-green outline-none"
              />
            </div>
            <Button variant="outline" size="sm"><Filter className="w-4 h-4 mr-2" /> Filter</Button>
          </div>
          <div className="text-sm text-muted-foreground">Showing {filteredTickets.length} tickets</div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-muted/50 border-b border-border">
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Number</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Short Description</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Caller</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Priority</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">State</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Category</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Assignment Group</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Assigned To</th>
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">SLA</th>
              </tr>
              <tr className="bg-white border-b border-border">
                <td className="p-1.5"><input value={columnFilters.number} onChange={e => setColumnFilters({...columnFilters, number: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.title} onChange={e => setColumnFilters({...columnFilters, title: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.caller} onChange={e => setColumnFilters({...columnFilters, caller: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.priority} onChange={e => setColumnFilters({...columnFilters, priority: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.status} onChange={e => setColumnFilters({...columnFilters, status: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.category} onChange={e => setColumnFilters({...columnFilters, category: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.assignmentGroup} onChange={e => setColumnFilters({...columnFilters, assignmentGroup: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"><input value={columnFilters.assignedTo} onChange={e => setColumnFilters({...columnFilters, assignedTo: e.target.value})} placeholder="Search" className="w-full p-1 border border-border rounded text-[11px] outline-none focus:ring-1 focus:ring-sn-green" /></td>
                <td className="p-1.5"></td>
              </tr>
            </thead>
            <tbody>
              {filteredTickets.map((ticket, idx) => {
                const assignedAgent = agents.find(a => a.id === ticket.assignedTo);
                return (
                   <tr key={ticket.id} className="data-table-row border-b border-border hover:bg-muted/10 transition-colors">
                    <td className="p-2">
                      <Link to={`/tickets/${ticket.id}`} className="font-mono text-[11px] font-bold text-blue-600 hover:underline">
                        {ticket.number || `INC000${idx + 1}`}
                      </Link>
                    </td>
                    <td className="p-2 text-[11px] font-medium">{ticket.title}</td>
                    <td className="p-2 text-[11px]">{ticket.caller}</td>
                    <td className="p-2">
                       <span className={cn(
                        "px-1 py-0.5 rounded text-[9px] font-bold uppercase",
                        ticket.priority?.includes("Critical") ? "bg-red-600 text-white" :
                        ticket.priority?.includes("High") ? "bg-red-100 text-red-700" : 
                        ticket.priority?.includes("Moderate") ? "bg-orange-100 text-orange-700" : "bg-blue-100 text-blue-700"
                      )}>
                        {ticket.priority}
                      </span>
                    </td>
                    <td className="p-2 text-[11px]">{ticket.status}</td>
                    <td className="p-2 text-[11px]">{ticket.category}</td>
                    <td className="p-2 text-[11px]">{ticket.assignmentGroup || "(empty)"}</td>
                    <td className="p-2 text-[11px]">{assignedAgent?.name || "(empty)"}</td>
                    <td className="p-2">
                      <div className="flex flex-col gap-1">
                        <SLATimer 
                          label="Resp" 
                          deadline={ticket.responseDeadline} 
                          metAt={ticket.firstResponseAt} 
                          isPaused={ticket.status === "On Hold" || ticket.status === "Waiting for Customer"}
                          onHoldStart={ticket.onHoldStart}
                          totalPausedTime={ticket.totalPausedTime}
                        />
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Create Ticket Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-4xl overflow-hidden animate-in fade-in zoom-in duration-200">
            <div className="p-4 border-b border-border flex items-center justify-between bg-muted/30">
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-muted-foreground">Incident</span>
                <span className="text-sm font-bold">New Record</span>
              </div>
              <div className="flex items-center gap-2">
                <Button variant="outline" size="sm" onClick={() => setIsModalOpen(false)}>Cancel</Button>
                <Button size="sm" className="bg-sn-green text-sn-dark font-bold" onClick={(e: any) => handleCreateTicket(e)} disabled={isSubmitting}>
                  {isSubmitting ? "Submitting..." : "Submit"}
                </Button>
              </div>
            </div>
            
            <form onSubmit={handleCreateTicket} className="p-6 overflow-y-auto max-h-[85vh]">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
                {/* Left Column */}
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Number</label>
                    <input disabled className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" value="INC (Auto-generated)" />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium uppercase leading-tight flex items-center justify-end gap-1">
                      <span className="text-red-500">*</span> Caller
                    </label>
                    <div className="col-span-2 flex gap-1">
                      <input 
                        required
                        value={newTicket.caller}
                        onChange={e => setNewTicket({...newTicket, caller: e.target.value})}
                        className="flex-grow p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8" 
                      />
                      <Button variant="outline" size="sm" className="h-8 w-8 p-0"><Search className="w-3 h-3" /></Button>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Category</label>
                    <select 
                      value={newTicket.category}
                      onChange={e => setNewTicket({...newTicket, category: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                    >
                      <option>Inquiry / Help</option>
                      <option>Software</option>
                      <option>Hardware</option>
                      <option>Network</option>
                      <option>Database</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Subcategory</label>
                    <select 
                      value={newTicket.subcategory}
                      onChange={e => setNewTicket({...newTicket, subcategory: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                    >
                       <option value="">-- None --</option>
                       <option>Antivirus</option>
                       <option>Email</option>
                       <option>Operating System</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Service</label>
                    <input 
                      value={newTicket.service}
                      onChange={e => setNewTicket({...newTicket, service: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8" 
                    />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Configuration item</label>
                    <input 
                      value={newTicket.cmdbItem}
                      onChange={e => setNewTicket({...newTicket, cmdbItem: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8" 
                    />
                  </div>
                </div>

                {/* Right Column */}
                <div className="space-y-4">
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">State</label>
                    <select 
                      disabled
                      className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs outline-none h-8"
                    >
                      <option>New</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Impact</label>
                    <select 
                      value={newTicket.impact}
                      onChange={e => setNewTicket({...newTicket, impact: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8"
                    >
                      <option>1 - High</option>
                      <option>2 - Medium</option>
                      <option>3 - Low</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Urgency</label>
                    <select 
                      value={newTicket.urgency}
                      onChange={e => setNewTicket({...newTicket, urgency: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8"
                    >
                      <option>1 - High</option>
                      <option>2 - Medium</option>
                      <option>3 - Low</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Priority</label>
                    <input 
                      disabled 
                      className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-bold text-blue-600 h-8" 
                      value={calculatePriority(newTicket.impact, newTicket.urgency)} 
                    />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assignment group</label>
                    <input 
                      value={newTicket.assignmentGroup}
                      onChange={e => setNewTicket({...newTicket, assignmentGroup: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8" 
                    />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assigned to</label>
                    <select 
                      value={newTicket.assignedTo}
                      onChange={e => setNewTicket({...newTicket, assignedTo: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8"
                    >
                      <option value="">-- None --</option>
                      {agents.map(agent => (
                        <option key={agent.id} value={agent.id}>{agent.name}</option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>

              {/* Full Width Fields */}
              <div className="mt-8 space-y-4">
                <div className="grid grid-cols-6 items-center gap-4">
                  <label className="text-[11px] text-right font-medium uppercase leading-tight flex items-center justify-end gap-1">
                    <span className="text-red-500">*</span> Short description
                  </label>
                  <input 
                    required
                    value={newTicket.title}
                    onChange={e => setNewTicket({...newTicket, title: e.target.value})}
                    className="col-span-5 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8" 
                  />
                </div>
                <div className="grid grid-cols-6 items-start gap-4">
                  <label className="text-[11px] text-right font-medium uppercase leading-tight mt-1">Description</label>
                  <textarea 
                    rows={4}
                    value={newTicket.description}
                    onChange={e => setNewTicket({...newTicket, description: e.target.value})}
                    className="col-span-5 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green resize-none h-32" 
                  />
                </div>
              </div>

              {/* Modal Footer */}
              <div className="flex justify-end gap-3 pt-6 border-t border-border mt-8">
                <Button 
                  type="button" 
                  variant="outline" 
                  onClick={() => setIsModalOpen(false)}
                  className="px-6 h-8 text-[11px] font-bold uppercase tracking-wider"
                >
                  Cancel
                </Button>
                <Button 
                  type="submit" 
                  disabled={isSubmitting}
                  className="bg-sn-green text-sn-dark hover:bg-sn-green/90 px-8 h-8 text-[11px] font-bold uppercase tracking-wider shadow-sm disabled:opacity-50"
                >
                  {isSubmitting ? "Submitting..." : "Submit"}
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

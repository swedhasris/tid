import React, { useEffect, useState } from "react";
import { collection, addDoc, query, onSnapshot, updateDoc, doc, serverTimestamp, orderBy, where } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { Plus, Filter, MoreVertical, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn, formatDate } from "@/lib/utils";
import { useServiceCatalog } from "../lib/serviceCatalog";

import { Link, useSearchParams } from "react-router-dom";

function SLATimer({ deadline, metAt, isPaused, onHoldStart, totalPausedTime = 0, label, waitUntil }: { deadline: string, metAt?: string, isPaused?: boolean, onHoldStart?: string, totalPausedTime?: number, label: string, waitUntil?: string | null }) {
  const [displayTime, setDisplayTime] = useState("");
  const [status, setStatus] = useState<"waiting" | "met" | "breached" | "active" | "paused">("active");

  useEffect(() => {
    if (metAt) { setStatus("met"); setDisplayTime("MET"); return; }

    // Resolution waiting for response — only when waitUntil is explicitly passed as null/empty
    if (waitUntil !== undefined && (waitUntil === null || waitUntil === "")) {
      setStatus("waiting"); setDisplayTime("—"); return;
    }

    const deadlineMs = new Date(deadline).getTime();
    if (isNaN(deadlineMs)) { setDisplayTime("--:--:--"); return; }

    const tick = () => {
      const now = Date.now();
      const effectiveNow = (isPaused && onHoldStart) ? new Date(onHoldStart).getTime() : now;
      const diff = deadlineMs - effectiveNow + (totalPausedTime || 0);

      if (diff <= 0) {
        setStatus("breached");
        const over = Math.abs(diff);
        const h = Math.floor(over / 3600000), m = Math.floor((over % 3600000) / 60000), s = Math.floor((over % 60000) / 1000);
        setDisplayTime(`-${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`);
      } else {
        setStatus(isPaused ? "paused" : "active");
        const h = Math.floor(diff / 3600000), m = Math.floor((diff % 3600000) / 60000), s = Math.floor((diff % 60000) / 1000);
        setDisplayTime(`${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`);
      }
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [deadline, metAt, isPaused, onHoldStart, totalPausedTime, waitUntil]);

  return (
    <div className="flex flex-col gap-0.5 min-w-[80px]">
      <span className="text-[9px] uppercase text-muted-foreground font-bold leading-none">{label}</span>
      <span className={cn(
        "text-[11px] font-mono font-bold leading-none",
        status === "met"     ? "text-green-600" :
        status === "breached"? "text-red-600" :
        status === "waiting" ? "text-gray-400" :
        status === "paused"  ? "text-orange-500" : "text-blue-600"
      )}>
        {displayTime}
      </span>
    </div>
  );
}

export function Tickets() {
  const { user, profile } = useAuth();
  const { categories, subcategories, serviceProviders, groups, members } = useServiceCatalog();
  const [searchParams] = useSearchParams();
  const filter = searchParams.get("filter");
  const action = searchParams.get("action");

  const [tickets, setTickets] = useState<any[]>([]);
  const [agents, setAgents] = useState<any[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [previewNumber, setPreviewNumber] = useState("");

  const openModal = () => {
    setPreviewNumber(`INC${Math.floor(1000000 + Math.random() * 9000000)}`);
    setIsModalOpen(true);
  };
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isAiLoading, setIsAiLoading] = useState(false);
  const [suggestedSolution, setSuggestedSolution] = useState<string | null>(null);

  useEffect(() => {
    if (action === "new") {
      openModal();
    }
  }, [action]);

  const [newTicket, setNewTicket] = useState({ 
    caller: "",
    category: "",
    categoryId: "",
    subcategory: "",
    subcategoryId: "",
    service: "",
    serviceId: "",
    serviceProvider: "",
    serviceOffering: "",
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
  const visibleCategories = categories.filter((item) => item.status === 'active');
  const visibleSubcategories = subcategories.filter(s => s.categoryId === newTicket.categoryId && s.status === 'active');
  const visibleProviders = serviceProviders.filter(p => p.subcategoryId === newTicket.subcategoryId && p.status === 'active');
  const visibleGroups = groups.filter(g => g.serviceProviderId === newTicket.serviceId && g.status === 'active');
  const visibleMembers = members.filter(m => m.groupId === newTicket.selectedGroupId && m.status === 'active');

  useEffect(() => {
    if (!newTicket.categoryId && visibleCategories[0]) {
      setNewTicket((prev) => ({
        ...prev,
        categoryId: visibleCategories[0].id,
        category: visibleCategories[0].name
      }));
    }
  }, [newTicket.categoryId, visibleCategories]);

  useEffect(() => {
    const firstSub = visibleSubcategories[0];
    if (newTicket.categoryId && !visibleSubcategories.some(s => s.id === newTicket.subcategoryId)) {
      setNewTicket(prev => ({
        ...prev,
        subcategoryId: firstSub?.id || "",
        subcategory: firstSub?.name || "",
        serviceId: "",
        service: "",
        assignmentGroup: ""
      }));
    }
  }, [newTicket.categoryId, visibleSubcategories]);

  useEffect(() => {
    const firstProv = visibleProviders[0];
    if (newTicket.subcategoryId && !visibleProviders.some(p => p.id === newTicket.serviceId)) {
      setNewTicket(prev => ({
        ...prev,
        serviceId: firstProv?.id || "",
        service: firstProv?.name || "",
        serviceProvider: firstProv?.name || "",
        assignmentGroup: ""
      }));
    }
  }, [newTicket.subcategoryId, visibleProviders]);

  useEffect(() => {
    const firstGroup = visibleGroups[0];
    if (newTicket.serviceId && !visibleGroups.some(g => g.name === newTicket.assignmentGroup)) {
      setNewTicket(prev => ({ 
        ...prev, 
        assignmentGroup: firstGroup?.name || "",
        selectedGroupId: firstGroup?.id || ""
      }));
    }
  }, [newTicket.serviceId, visibleGroups]);

  useEffect(() => {
    if (visibleMembers.length > 0 && !newTicket.assignedTo) {
      // Auto-assign to the first member (or logic can be added for Round Robin)
      setNewTicket(prev => ({ ...prev, assignedTo: visibleMembers[0].userId }));
    }
  }, [visibleMembers]);

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

  const handleAIAssist = async () => {
    const shortDesc = newTicket.title;
    if (!shortDesc) {
      alert("Please enter a Short Description first, then click Autofill with AI.");
      return;
    }

    setIsAiLoading(true);
    setSuggestedSolution(null);

    try {
      // Run classify + description generation in parallel using our server endpoints
      const [classifyRes, suggestRes] = await Promise.all([
        fetch('/api/ai/classify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ text: shortDesc }),
        }),
        fetch('/api/ai/suggest', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ text: shortDesc }),
        }),
      ]);

      const classData = await classifyRes.json();
      const suggestData = await suggestRes.json();

      if (!classifyRes.ok) throw new Error(classData.error || "Classification failed");
      if (!suggestRes.ok) throw new Error(suggestData.error || "Suggestion failed");

      setNewTicket(prev => ({
        ...prev,
        category: classData.category || prev.category,
        impact: classData.priority === 'Critical' || classData.priority === 'High' ? '1 - High' : classData.priority === 'Medium' ? '2 - Medium' : '3 - Low',
        urgency: classData.priority === 'Critical' || classData.priority === 'High' ? '1 - High' : classData.priority === 'Medium' ? '2 - Medium' : '3 - Low',
        description: suggestData.suggestion || prev.description,
      }));

      if (suggestData.suggestion) {
        setSuggestedSolution(suggestData.suggestion);
      }
    } catch (e) {
      console.error(e);
      alert("AI autofill failed. Please fill in the description manually.");
    } finally {
      setIsAiLoading(false);
    }
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
      alert("Please fill in all required fields (Reporting User and Short description).");
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
      // Resolution deadline is from now (full window), but the timer won't start until response is given
      const resolutionDeadline = new Date(now + ((matchingPolicy.responseTimeHours || 4) + (matchingPolicy.resolutionTimeHours || 24)) * 60 * 60 * 1000);

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
      const assignmentGroup = newTicket.assignmentGroup || visibleGroups[0]?.name || "Service Desk";

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
        categoryId: "",
        subcategory: "",
        subcategoryId: "",
        service: "",
        serviceId: "",
        serviceProvider: "",
        serviceOffering: "",
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
        <Button onClick={() => openModal()} className="bg-sn-green text-sn-dark font-bold">
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
                <th className="data-table-header p-2 text-[11px] font-bold uppercase tracking-tight">Reporting User</th>
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
                        <SLATimer
                          label="Res"
                          deadline={ticket.resolutionDeadline}
                          metAt={ticket.resolvedAt}
                          isPaused={ticket.status === "On Hold" || ticket.status === "Waiting for Customer"}
                          onHoldStart={ticket.onHoldStart}
                          totalPausedTime={ticket.totalPausedTime}
                          waitUntil={ticket.firstResponseAt ?? null}
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
                    <input disabled className="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono"
                      value={previewNumber}
                    />
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium uppercase leading-tight flex items-center justify-end gap-1">
                      <span className="text-red-500">*</span> Reporting User
                    </label>
                    <div className="col-span-2 flex gap-1">
                      <input 
                        required
                        placeholder="Who is reporting this incident?"
                        value={newTicket.caller}
                        onChange={e => setNewTicket({...newTicket, caller: e.target.value})}
                        className="flex-grow p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8" 
                      />
                      <Button variant="outline" size="sm" className="h-8 w-8 p-0"><Search className="w-3 h-3" /></Button>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium uppercase leading-tight flex items-center justify-end gap-1">
                      Affected User
                    </label>
                    <div className="col-span-2 flex gap-1">
                      <input 
                        placeholder="Who is affected? (if different)"
                        value={newTicket.affectedUser || ''}
                        onChange={e => setNewTicket({...newTicket, affectedUser: e.target.value})}
                        className="flex-grow p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8" 
                      />
                      <Button variant="outline" size="sm" className="h-8 w-8 p-0"><Search className="w-3 h-3" /></Button>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Category</label>
                    <select 
                      value={newTicket.categoryId}
                      onChange={e => {
                        const category = visibleCategories.find((item) => item.id === e.target.value);
                        setNewTicket({
                          ...newTicket,
                          categoryId: e.target.value,
                          category: category?.name || "",
                          subcategoryId: "",
                          subcategory: "",
                          serviceId: "",
                          service: "",
                          serviceProvider: "",
                          assignmentGroup: ""
                        });
                      }}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                    >
                      {visibleCategories.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Subcategory</label>
                    <select 
                      value={newTicket.subcategoryId}
                      onChange={e => {
                        const subcategory = visibleSubcategories.find((item) => item.id === e.target.value);
                        setNewTicket({
                          ...newTicket,
                          subcategoryId: e.target.value,
                          subcategory: subcategory?.name || "",
                          serviceId: "",
                          service: "",
                          serviceProvider: "",
                          assignmentGroup: ""
                        });
                      }}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                    >
                      <option value="">-- None --</option>
                      {visibleSubcategories.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Service Provider</label>
                    <select
                      value={newTicket.serviceId}
                      onChange={e => {
                        const service = visibleProviders.find((item) => item.id === e.target.value);
                        setNewTicket({
                          ...newTicket,
                          serviceId: e.target.value,
                          service: service?.name || "",
                          serviceProvider: service?.name || "",
                          assignmentGroup: ""
                        });
                      }}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green outline-none h-8"
                    >
                      <option value="">-- Select Service --</option>
                      {visibleProviders.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
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
                    <select
                      value={newTicket.assignmentGroup}
                      onChange={e => {
                        const group = visibleGroups.find(g => g.name === e.target.value);
                        setNewTicket({...newTicket, assignmentGroup: e.target.value, selectedGroupId: group?.id || ""});
                      }}
                      className="col-span-2 p-1.5 border border-border rounded text-xs outline-none focus:ring-1 focus:ring-sn-green h-8"
                    >
                      <option value="">-- Auto Assign --</option>
                      {visibleGroups.map((item) => (
                        <option key={item.id} value={item.name}>
                          {item.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="grid grid-cols-3 items-center gap-4">
                    <label className="text-[11px] text-right font-medium text-muted-foreground uppercase leading-tight">Assigned to</label>
                    <select 
                      value={newTicket.assignedTo}
                      onChange={e => setNewTicket({...newTicket, assignedTo: e.target.value})}
                      className="col-span-2 p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8"
                    >
                      <option value="">-- Select Member --</option>
                      {visibleMembers.map(m => (
                        <option key={m.id} value={m.userId}>{m.userName}</option>
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
                  <div className="col-span-5 flex gap-2">
                    <input 
                      required
                      value={newTicket.title}
                      onChange={e => setNewTicket({...newTicket, title: e.target.value})}
                      className="flex-grow p-1.5 border border-border rounded text-xs focus:ring-1 focus:ring-sn-green h-8" 
                    />
                    <Button 
                      type="button"
                      onClick={handleAIAssist}
                      disabled={isAiLoading}
                      className="bg-purple-600 hover:bg-purple-700 text-white h-8 text-[11px]"
                    >
                      {isAiLoading ? "Analyzing..." : "Autofill with AI"}
                    </Button>
                  </div>
                </div>
                <div className="grid grid-cols-6 items-start gap-4">
                  <label className="text-[11px] text-right font-medium uppercase leading-tight mt-1">Description</label>
                  <textarea 
                    rows={4}
                    value={newTicket.description}
                    onChange={e => setNewTicket({...newTicket, description: e.target.value})}
                    className={`col-span-5 p-1.5 border rounded text-xs focus:ring-1 focus:ring-sn-green resize-none h-32 transition-all ${suggestedSolution ? 'border-purple-400 ring-1 ring-purple-300 bg-purple-50' : 'border-border'}`}
                    placeholder="Describe the issue in detail... or use Autofill with AI above"
                  />
                </div>
              </div>

              {/* Suggested Solution Box */}
              {suggestedSolution && (
                <div className="mt-4 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                  <h4 className="text-purple-800 font-semibold mb-2 flex items-center gap-2">
                    <span>✨</span> AI filled your description
                    <span className="text-[10px] font-normal text-purple-500 ml-auto">You can edit it above</span>
                  </h4>
                  <p className="text-xs text-purple-700 italic line-clamp-3">{suggestedSolution}</p>
                  <button type="button" onClick={() => setSuggestedSolution(null)}
                    className="mt-2 text-[10px] text-purple-400 hover:text-purple-600 underline">
                    Dismiss
                  </button>
                </div>
              )}

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
                  disabled={isSubmitting || suggestedSolution !== null}
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

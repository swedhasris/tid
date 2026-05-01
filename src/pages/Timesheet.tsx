import React, { useState, useEffect } from "react";
import { Clock, ChevronLeft, ChevronRight, Plus, Pencil, Trash2, CheckCircle, AlertCircle, BarChart2, Lock } from "lucide-react";
import { useAuth } from "../contexts/AuthContext";
import { db } from "../lib/firebase";
import {
  collection, query, where, getDocs, addDoc, updateDoc, deleteDoc,
  doc, serverTimestamp, orderBy
} from "firebase/firestore";
import { Link } from "react-router-dom";
import { formatDate as formatDateUtils } from "../lib/utils";

const STATUS_COLORS: Record<string, string> = {
  Draft: "bg-gray-100 text-gray-700",
  Submitted: "bg-blue-100 text-blue-700",
  Approved: "bg-green-100 text-green-700",
  Rejected: "bg-red-100 text-red-700",
};

const DEFAULT_TASKS = [
  "General Support", "Ticket Resolution", "Project Work",
  "Training", "Meeting", "Documentation",
  "System Maintenance", "Bug Fix", "Feature Development", "Code Review"
];

function getMonday(date: Date): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

function formatDate(d: Date): string {
  return d.toISOString().split("T")[0];
}

export function Timesheet() {
  const { user, profile } = useAuth();
  const [weekOffset, setWeekOffset] = useState(0);
  const [timesheet, setTimesheet] = useState<any>(null);
  const [timeCards, setTimeCards] = useState<any[]>([]);
  const [tasks, setTasks] = useState<string[]>(DEFAULT_TASKS);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [editEntry, setEditEntry] = useState<any>(null);
  const [selectedDate, setSelectedDate] = useState("");
  const [form, setForm] = useState({ task: "", hours: "", description: "" });
  const [errors, setErrors] = useState<string[]>([]);
  const [saving, setSaving] = useState(false);

  const monday = getMonday(new Date(Date.now() + weekOffset * 7 * 86400000));
  const weekStart = formatDate(monday);
  const weekEnd = formatDate(new Date(monday.getTime() + 6 * 86400000));

  const weekDays = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(monday.getTime() + i * 86400000);
    return {
      date: formatDate(d),
      dayName: d.toLocaleDateString("en-US", { weekday: "short" }),
      fullDate: d.toLocaleDateString("en-US", { month: "short", day: "numeric" }),
    };
  });

  useEffect(() => { loadWeek(); }, [weekOffset, user]);

  async function loadWeek() {
    if (!user) return;
    setLoading(true);
    try {
      // Find or create timesheet
      const tsQuery = query(
        collection(db, "timesheets"),
        where("userId", "==", user.uid),
        where("weekStart", "==", weekStart)
      );
      const tsSnap = await getDocs(tsQuery);
      let ts: any;
      if (tsSnap.empty) {
        const ref = await addDoc(collection(db, "timesheets"), {
          userId: user.uid,
          weekStart,
          weekEnd,
          status: "Draft",
          totalHours: 0,
          createdAt: serverTimestamp(),
          updatedAt: serverTimestamp(),
        });
        ts = { id: ref.id, userId: user.uid, weekStart, weekEnd, status: "Draft", totalHours: 0 };
      } else {
        ts = { id: tsSnap.docs[0].id, ...tsSnap.docs[0].data() };
      }
      setTimesheet(ts);

      // Load time cards
      const cardsQuery = query(
        collection(db, "timeCards"),
        where("timesheetId", "==", ts.id),
        orderBy("entryDate", "asc")
      );
      const cardsSnap = await getDocs(cardsQuery);
      setTimeCards(cardsSnap.docs.map(d => ({ id: d.id, ...d.data() })));
    } catch (e) {
      console.error(e);
    }
    setLoading(false);
  }

  async function saveEntry() {
    const hrs = parseFloat(form.hours);
    const errs: string[] = [];
    if (!form.task) errs.push("Task is required");
    if (!hrs || hrs <= 0) errs.push("Hours must be greater than 0");
    if (hrs > 24) errs.push("Hours cannot exceed 24");

    // Daily limit check
    const dayTotal = timeCards
      .filter(c => c.entryDate === selectedDate && c.id !== editEntry?.id)
      .reduce((sum, c) => sum + (c.hoursWorked || 0), 0);
    if (dayTotal + hrs > 24) errs.push(`Total hours for this day would exceed 24 (currently ${dayTotal} hrs)`);

    if (errs.length) { setErrors(errs); return; }

    setSaving(true);
    try {
      const data = {
        timesheetId: timesheet.id,
        userId: user!.uid,
        entryDate: selectedDate,
        task: form.task,
        hoursWorked: hrs,
        description: form.description,
        status: "Draft",
        updatedAt: serverTimestamp(),
      };

      if (editEntry) {
        await updateDoc(doc(db, "timeCards", editEntry.id), data);
      } else {
        await addDoc(collection(db, "timeCards"), { ...data, createdAt: serverTimestamp() });
      }

      // Recalculate total
      const allCards = await getDocs(query(collection(db, "timeCards"), where("timesheetId", "==", timesheet.id)));
      const total = allCards.docs.reduce((s, d) => s + (d.data().hoursWorked || 0), 0);
      await updateDoc(doc(db, "timesheets", timesheet.id), { totalHours: total, updatedAt: serverTimestamp() });

      setShowModal(false);
      setEditEntry(null);
      setForm({ task: "", hours: "", description: "" });
      setErrors([]);
      loadWeek();
    } catch (e) { console.error(e); }
    setSaving(false);
  }

  async function deleteEntry(cardId: string) {
    if (!confirm("Delete this entry?")) return;
    await deleteDoc(doc(db, "timeCards", cardId));
    const allCards = await getDocs(query(collection(db, "timeCards"), where("timesheetId", "==", timesheet.id)));
    const total = allCards.docs.reduce((s, d) => s + (d.data().hoursWorked || 0), 0);
    await updateDoc(doc(db, "timesheets", timesheet.id), { totalHours: total, updatedAt: serverTimestamp() });
    loadWeek();
  }

  async function submitTimesheet() {
    if (!confirm("Submit this timesheet for approval? You won't be able to edit it after.")) return;
    if (timeCards.length === 0) { alert("Cannot submit an empty timesheet."); return; }
    await updateDoc(doc(db, "timesheets", timesheet.id), {
      status: "Submitted", submittedAt: serverTimestamp(), updatedAt: serverTimestamp()
    });
    loadWeek();
  }

  function openAdd(date: string) {
    setSelectedDate(date);
    setEditEntry(null);
    setForm({ task: "", hours: "", description: "" });
    setErrors([]);
    setShowModal(true);
  }

  function openEdit(card: any) {
    setSelectedDate(card.entryDate);
    setEditEntry(card);
    setForm({ task: card.task || "", hours: String(card.hoursWorked || ""), description: card.description || "" });
    setErrors([]);
    setShowModal(true);
  }

  const canEdit = timesheet?.status === "Draft" || timesheet?.status === "Rejected";
  const weekTotal = timeCards.reduce((s, c) => s + (c.hoursWorked || 0), 0);

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between pb-4 border-b border-border">
        <div>
          <h1 className="text-2xl font-bold text-sn-dark">Timesheet</h1>
          <p className="text-muted-foreground text-sm">Log and manage your weekly working hours</p>
        </div>
        <div className="flex items-center gap-3">
          <Link to="/timesheet/reports" className="flex items-center gap-2 px-3 py-2 border border-border rounded text-sm hover:bg-muted transition-colors">
            <BarChart2 className="w-4 h-4" /> Reports
          </Link>
          <span className={`px-3 py-1 rounded-full text-sm font-semibold ${STATUS_COLORS[timesheet?.status] || STATUS_COLORS.Draft}`}>
            {timesheet?.status || "Draft"}
          </span>
          {canEdit && (
            <button onClick={submitTimesheet} className="flex items-center gap-2 bg-sn-green text-sn-dark px-4 py-2 rounded font-semibold text-sm hover:opacity-90 transition-opacity">
              <CheckCircle className="w-4 h-4" /> Submit Timesheet
            </button>
          )}
        </div>
      </div>

      {/* Rejection banner */}
      {timesheet?.status === "Rejected" && timesheet?.rejectionReason && (
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg flex items-start gap-3">
          <AlertCircle className="w-5 h-5 mt-0.5 shrink-0" />
          <div>
            <div className="font-semibold">Timesheet Rejected</div>
            <div className="text-sm mt-1">{timesheet.rejectionReason}</div>
          </div>
        </div>
      )}

      {/* Approved banner */}
      {timesheet?.status === "Approved" && (
        <div className="bg-green-50 border border-green-300 text-green-800 p-4 rounded-lg flex items-start gap-3">
          <CheckCircle className="w-5 h-5 mt-0.5 shrink-0 text-green-600" />
          <div className="flex-grow">
            <div className="font-bold text-green-700">✅ Timesheet Approved</div>
            <div className="text-sm mt-1 text-green-600">
              Your timesheet for this week has been approved.
              {timesheet?.approvedAt && (
                <span className="ml-2 text-xs opacity-75">
                  on {formatDateUtils(timesheet.approvedAt)}
                </span>
              )}
            </div>
          </div>
          <div className="text-right shrink-0">
            <div className="text-2xl font-bold text-green-700">{weekTotal.toFixed(2)}</div>
            <div className="text-xs text-green-600 font-medium">hrs approved</div>
          </div>
        </div>
      )}

      {/* Submitted banner */}
      {timesheet?.status === "Submitted" && (
        <div className="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg flex items-start gap-3">
          <Clock className="w-5 h-5 mt-0.5 shrink-0" />
          <div>
            <div className="font-semibold">Timesheet Submitted — Awaiting Approval</div>
            <div className="text-sm mt-1 text-blue-600">Your timesheet is under review. You cannot make changes until it is approved or rejected.</div>
          </div>
        </div>
      )}

      {/* Week Navigation */}
      <div className="flex items-center justify-between">
        <button onClick={() => setWeekOffset(w => w - 1)} className="flex items-center gap-1 px-3 py-2 border border-border rounded text-sm hover:bg-muted transition-colors">
          <ChevronLeft className="w-4 h-4" /> Previous Week
        </button>
        <div className="text-center">
          <div className="font-semibold">
            {new Date(weekStart).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })} —{" "}
            {new Date(weekEnd).toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" })}
          </div>
          <div className="text-sm text-muted-foreground">Total: <strong>{weekTotal.toFixed(2)}</strong> hours</div>
        </div>
        <button onClick={() => setWeekOffset(w => w + 1)} className="flex items-center gap-1 px-3 py-2 border border-border rounded text-sm hover:bg-muted transition-colors">
          Next Week <ChevronRight className="w-4 h-4" />
        </button>
      </div>

      {/* Weekly Grid */}
      {loading ? (
        <div className="flex items-center justify-center py-20">
          <div className="w-8 h-8 border-4 border-sn-green border-t-transparent rounded-full animate-spin" />
        </div>
      ) : (
        <div className={`grid grid-cols-7 gap-3 ${timesheet?.status === "Approved" ? "opacity-75 pointer-events-none" : ""}`}>
          {weekDays.map(day => {
            const entries = timeCards.filter(c => c.entryDate === day.date);
            const dayTotal = entries.reduce((s, c) => s + (c.hoursWorked || 0), 0);
            const isWeekend = ["Sat", "Sun"].includes(day.dayName);
            return (
              <div key={day.date} className={`rounded-lg border border-border flex flex-col min-h-[280px] ${isWeekend ? "bg-muted/40" : "bg-white"}`}>
                <div className={`p-3 border-b border-border rounded-t-lg ${isWeekend ? "bg-muted/30" : "bg-muted/10"}`}>
                  <div className="text-xs font-bold uppercase text-muted-foreground">{day.dayName}</div>
                  <div className="text-base font-semibold">{day.fullDate}</div>
                  <div className="text-sm font-bold text-sn-green mt-1">{dayTotal.toFixed(1)} hrs</div>
                </div>
                <div className="flex-grow p-2 space-y-1.5 overflow-y-auto max-h-[180px]">
                  {entries.length === 0 ? (
                    <div className="text-center py-4 text-xs text-muted-foreground italic">No entries</div>
                  ) : entries.map(entry => (
                    <div key={entry.id} className="p-2 bg-muted/30 rounded border border-border text-xs group relative hover:bg-muted/50 transition-colors">
                      <div className="font-semibold truncate pr-10">{entry.task}</div>
                      <div className="text-muted-foreground">{entry.hoursWorked} hrs</div>
                      {entry.description && <div className="text-muted-foreground truncate mt-0.5">{entry.description}</div>}
                      {canEdit && (
                        <div className="absolute top-1 right-1 opacity-0 group-hover:opacity-100 flex gap-0.5">
                          <button onClick={() => openEdit(entry)} className="p-1 hover:bg-white rounded" title="Edit">
                            <Pencil className="w-3 h-3" />
                          </button>
                          <button onClick={() => deleteEntry(entry.id)} className="p-1 hover:bg-red-100 text-red-500 rounded" title="Delete">
                            <Trash2 className="w-3 h-3" />
                          </button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
                {canEdit && (
                  <div className="p-2 border-t border-border">
                    <button onClick={() => openAdd(day.date)} className="w-full flex items-center justify-center gap-1 py-1.5 border border-border rounded text-xs hover:bg-muted transition-colors">
                      <Plus className="w-3 h-3" /> Add Entry
                    </button>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4">
        {[
          { label: "Week Total", value: `${weekTotal.toFixed(2)} hrs`, color: "text-sn-dark" },
          { label: "Daily Average", value: `${(weekTotal / 7).toFixed(2)} hrs`, color: "text-blue-600" },
          { label: "Entries", value: timeCards.length, color: "text-purple-600" },
          { label: "Status", value: timesheet?.status || "Draft", color: timesheet?.status === "Approved" ? "text-green-600" : timesheet?.status === "Rejected" ? "text-red-600" : "text-gray-700" },
        ].map(s => (
          <div key={s.label} className="bg-white rounded-lg border border-border p-4">
            <div className="text-xs text-muted-foreground uppercase font-bold tracking-wide">{s.label}</div>
            <div className={`text-2xl font-bold mt-1 ${s.color}`}>{s.value}</div>
          </div>
        ))}
      </div>

      {/* Add/Edit Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={e => e.target === e.currentTarget && setShowModal(false)}>
          <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div className="p-4 border-b border-border flex items-center justify-between bg-muted/30">
              <h3 className="font-semibold">{editEntry ? "Edit Time Entry" : "Add Time Entry"}</h3>
              <button onClick={() => setShowModal(false)} className="p-1 hover:bg-muted rounded">✕</button>
            </div>
            <div className="p-6 space-y-4">
              <div>
                <label className="text-sm font-medium block mb-1">Date</label>
                <input type="text" value={new Date(selectedDate + "T12:00:00").toLocaleDateString("en-US", { weekday: "long", year: "numeric", month: "long", day: "numeric" })} disabled className="w-full p-2 bg-muted/30 border border-border rounded text-sm" />
              </div>
              <div>
                <label className="text-sm font-medium block mb-1">Task <span className="text-red-500">*</span></label>
                <select value={form.task} onChange={e => setForm(f => ({ ...f, task: e.target.value }))} className="w-full p-2 border border-border rounded text-sm">
                  <option value="">Select a task...</option>
                  {tasks.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div>
                <label className="text-sm font-medium block mb-1">Hours Worked <span className="text-red-500">*</span></label>
                <input type="number" step="0.5" min="0.5" max="24" value={form.hours} onChange={e => setForm(f => ({ ...f, hours: e.target.value }))} className="w-full p-2 border border-border rounded text-sm" placeholder="e.g. 8" />
                <p className="text-xs text-muted-foreground mt-1">Max 24 hours per day total</p>
              </div>
              <div>
                <label className="text-sm font-medium block mb-1">Description / Notes</label>
                <textarea rows={3} value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} className="w-full p-2 border border-border rounded text-sm resize-none" placeholder="Describe the work done..." />
              </div>
              {errors.length > 0 && (
                <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded text-sm">
                  {errors.map((e, i) => <div key={i}>• {e}</div>)}
                </div>
              )}
              <div className="flex justify-end gap-3 pt-2 border-t border-border">
                <button onClick={() => setShowModal(false)} className="px-4 py-2 border border-border rounded text-sm hover:bg-muted transition-colors">Cancel</button>
                <button onClick={saveEntry} disabled={saving} className="flex items-center gap-2 bg-sn-green text-sn-dark px-4 py-2 rounded text-sm font-semibold hover:opacity-90 disabled:opacity-50">
                  {saving ? "Saving..." : "Save Entry"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

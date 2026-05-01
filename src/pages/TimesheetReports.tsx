import React, { useState, useEffect } from "react";
import { BarChart2, ArrowLeft } from "lucide-react";
import { Link, useSearchParams } from "react-router-dom";
import { useAuth } from "../contexts/AuthContext";
import { db } from "../lib/firebase";
import { collection, query, where, getDocs, orderBy } from "firebase/firestore";
import { formatDate } from "../lib/utils";

export function TimesheetReports() {
  const { user, profile } = useAuth();
  const [searchParams] = useSearchParams();
  const [timesheets, setTimesheets] = useState<any[]>([]);
  const [allCards, setAllCards] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { loadData(); }, [user]);

  async function loadData() {
    if (!user) return;
    setLoading(true);
    try {
      const tsSnap = await getDocs(query(collection(db, "timesheets"), where("userId", "==", user.uid), orderBy("weekStart", "desc")));
      const tsList = tsSnap.docs.map(d => ({ id: d.id, ...d.data() }));
      setTimesheets(tsList);

      const cards: any[] = [];
      for (const ts of tsList) {
        const cSnap = await getDocs(query(collection(db, "timeCards"), where("timesheetId", "==", ts.id)));
        cSnap.docs.forEach(d => cards.push({ id: d.id, ...d.data() }));
      }
      setAllCards(cards);
    } catch (e) { console.error(e); }
    setLoading(false);
  }

  const totalHours = timesheets.reduce((s, t) => s + (t.totalHours || 0), 0);
  const approvedHours = timesheets.filter(t => t.status === "Approved").reduce((s, t) => s + (t.totalHours || 0), 0);
  const avgPerWeek = timesheets.length ? totalHours / timesheets.length : 0;

  // Hours by task
  const taskMap: Record<string, number> = {};
  allCards.forEach(c => { taskMap[c.task || "Unknown"] = (taskMap[c.task || "Unknown"] || 0) + (c.hoursWorked || 0); });
  const taskData = Object.entries(taskMap).sort((a, b) => b[1] - a[1]);
  const maxTaskHours = taskData.length ? taskData[0][1] : 1;

  const STATUS_COLORS: Record<string, string> = {
    Draft: "bg-gray-100 text-gray-700",
    Submitted: "bg-blue-100 text-blue-700",
    Approved: "bg-green-100 text-green-700",
    Rejected: "bg-red-100 text-red-700",
  };

  const statusFilter = searchParams.get("status");
  const visibleTimesheets = statusFilter ? timesheets.filter((item) => item.status === statusFilter) : timesheets;

  return (
    <div className="space-y-6 max-w-6xl mx-auto">
      <div className="flex items-center justify-between pb-4 border-b border-border">
        <div className="flex items-center gap-3">
          <Link to="/timesheet" className="p-2 hover:bg-muted rounded transition-colors">
            <ArrowLeft className="w-4 h-4" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-sn-dark">Timesheet Reports</h1>
            <p className="text-sm text-muted-foreground">
              {statusFilter ? `${statusFilter} timesheets only` : "Analytics for your logged hours"}
            </p>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-20">
          <div className="w-8 h-8 border-4 border-sn-green border-t-transparent rounded-full animate-spin" />
        </div>
      ) : (
        <>
          {/* Stats */}
          <div className="grid grid-cols-4 gap-4">
            {[
              { label: "Total Hours", value: totalHours.toFixed(1), sub: `${timesheets.length} weeks`, color: "text-sn-dark" },
              { label: "Weekly Average", value: avgPerWeek.toFixed(1), sub: "hrs/week", color: "text-blue-600" },
              { label: "Approved Hours", value: approvedHours.toFixed(1), sub: `${totalHours > 0 ? ((approvedHours / totalHours) * 100).toFixed(0) : 0}% of total`, color: "text-green-600" },
              { label: "Tasks Used", value: taskData.length, sub: "different tasks", color: "text-purple-600" },
            ].map(s => (
              <div key={s.label} className="bg-white rounded-lg border border-border p-5">
                <div className="text-xs font-bold text-muted-foreground uppercase tracking-wider">{s.label}</div>
                <div className={`text-3xl font-bold mt-2 ${s.color}`}>{s.value}</div>
                <div className="text-sm text-muted-foreground mt-1">{s.sub}</div>
              </div>
            ))}
          </div>

          {/* Hours by Task */}
          <div className="bg-white rounded-lg border border-border overflow-hidden">
            <div className="p-4 border-b border-border bg-muted/30">
              <h3 className="font-semibold">Hours by Task</h3>
            </div>
            <div className="p-4 space-y-3">
              {taskData.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">No data yet.</p>
              ) : taskData.map(([task, hrs]) => (
                <div key={task} className="flex items-center gap-4">
                  <div className="w-40 text-sm font-medium truncate">{task}</div>
                  <div className="flex-grow h-4 bg-muted rounded-full overflow-hidden">
                    <div className="h-full bg-sn-green rounded-full transition-all" style={{ width: `${(hrs / maxTaskHours) * 100}%` }} />
                  </div>
                  <div className="w-20 text-right text-sm font-bold">{hrs.toFixed(1)} hrs</div>
                  <div className="w-12 text-right text-xs text-muted-foreground">{totalHours > 0 ? ((hrs / totalHours) * 100).toFixed(0) : 0}%</div>
                </div>
              ))}
            </div>
          </div>

          {/* Weekly History */}
          <div className="bg-white rounded-lg border border-border overflow-hidden">
            <div className="p-4 border-b border-border bg-muted/30">
              <h3 className="font-semibold">Weekly History</h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="bg-muted/50 border-b border-border text-xs font-bold uppercase tracking-wide text-muted-foreground">
                    <th className="p-3 text-left">Week</th>
                    <th className="p-3 text-right">Total Hours</th>
                    <th className="p-3 text-center">Status</th>
                    <th className="p-3 text-left">Submitted</th>
                  </tr>
                </thead>
                <tbody>
                  {visibleTimesheets.length === 0 ? (
                    <tr><td colSpan={4} className="p-8 text-center text-muted-foreground">No timesheets yet.</td></tr>
                  ) : visibleTimesheets.map(ts => (
                    <tr key={ts.id} className="border-b border-border hover:bg-muted/10">
                      <td className="p-3 text-sm font-medium">{ts.weekStart} → {ts.weekEnd}</td>
                      <td className="p-3 text-right font-bold">{(ts.totalHours || 0).toFixed(2)} hrs</td>
                      <td className="p-3 text-center">
                        <span className={`px-2 py-0.5 rounded text-xs font-semibold ${STATUS_COLORS[ts.status] || STATUS_COLORS.Draft}`}>{ts.status}</span>
                      </td>
                      <td className="p-3 text-sm text-muted-foreground">
                        {formatDate(ts.submittedAt)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

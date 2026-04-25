import React, { useEffect, useState } from "react";
import { collection, query, onSnapshot, where } from "firebase/firestore";
import { db, handleFirestoreError, OperationType } from "../lib/firebase";
import { useAuth } from "../contexts/AuthContext";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { Map } from "lucide-react";

export function Reports() {
  const { user, profile } = useAuth();
  const [data, setData] = useState<any[]>([]);
  const [categoryData, setCategoryData] = useState<any[]>([]);
  const [slaData, setSlaData] = useState<any[]>([]);

  useEffect(() => {
    if (!user || !profile) return;

    const isAgent = profile.role === "agent" || profile.role === "admin" || profile.role === "super_admin";
    const ticketsRef = collection(db, "tickets");
    const q = isAgent 
      ? query(ticketsRef) 
      : query(ticketsRef, where("createdBy", "==", user.uid));

    const unsubscribe = onSnapshot(q, (snapshot) => {
      const tickets = snapshot.docs.map(doc => doc.data());
      
      // Status Distribution
      const statusCounts: any = {};
      tickets.forEach((t: any) => {
        statusCounts[t.status] = (statusCounts[t.status] || 0) + 1;
      });
      setData(Object.keys(statusCounts).map(status => ({ name: status, count: statusCounts[status] })));

      // Category Distribution
      const catCounts: any = {};
      tickets.forEach((t: any) => {
        catCounts[t.category] = (catCounts[t.category] || 0) + 1;
      });
      setCategoryData(Object.keys(catCounts).map(cat => ({ name: cat, value: catCounts[cat] })));

      // SLA Compliance
      const slaCounts = { "Within SLA": 0, "At Risk": 0, "Breached": 0 };
      tickets.forEach((t: any) => {
        const resStatus = t.resolutionSlaStatus || "In Progress";
        const respStatus = t.responseSlaStatus || "In Progress";
        
        if (resStatus === "Breached" || respStatus === "Breached") {
          slaCounts["Breached"]++;
        } else if (resStatus === "At Risk" || respStatus === "At Risk") {
          slaCounts["At Risk"]++;
        } else {
          slaCounts["Within SLA"]++;
        }
      });
      setSlaData([
        { name: "Within SLA", value: slaCounts["Within SLA"] },
        { name: "At Risk", value: slaCounts["At Risk"] },
        { name: "Breached", value: slaCounts["Breached"] }
      ]);
    }, (error) => {
      handleFirestoreError(error, OperationType.LIST, "tickets");
    });
    return unsubscribe;
  }, []);

  const COLORS = ["#81B532", "#151B26", "#3b82f6", "#ef4444", "#f59e0b"];
  const SLA_COLORS = ["#81B532", "#f59e0b", "#ef4444"];

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Reports & Analytics</h1>
        <p className="text-muted-foreground">Visual insights into service desk performance.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* SLA Compliance Widget */}
        <div className="sn-card">
          <h3 className="text-lg font-bold mb-6">SLA Compliance Rate</h3>
          <div className="h-64 w-full relative">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={slaData.some(d => d.value > 0) ? slaData : [{ name: "No Data", value: 1 }]}
                  cx="50%"
                  cy="50%"
                  innerRadius={75}
                  outerRadius={105}
                  paddingAngle={slaData.some(d => d.value > 0) ? 3 : 0}
                  dataKey="value"
                  startAngle={90}
                  endAngle={-270}
                >
                  {(slaData.some(d => d.value > 0) ? slaData : [{ name: "No Data", value: 1 }]).map((entry, index) => (
                    <Cell
                      key={`cell-${index}`}
                      fill={slaData.some(d => d.value > 0) ? SLA_COLORS[index % SLA_COLORS.length] : "#e2e8f0"}
                    />
                  ))}
                </Pie>
                <Tooltip
                  formatter={(value: any, name: any) => [value + " tickets", name]}
                  contentStyle={{ borderRadius: "8px", border: "1px solid #e2e8f0", fontSize: "12px" }}
                />
              </PieChart>
            </ResponsiveContainer>
            {/* Center text — pointer-events-none so it never blocks tooltip */}
            <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none select-none">
              <span className="text-4xl font-bold text-sn-dark dark:text-white leading-none">
                {(() => {
                  const total = slaData.reduce((sum, d) => sum + d.value, 0);
                  const withinSla = slaData[0]?.value ?? 0;
                  return total > 0 ? Math.round((withinSla / total) * 100) : 0;
                })()}%
              </span>
              <span className="text-xs text-muted-foreground font-medium mt-1">Compliance</span>
            </div>
          </div>
          {/* Legend */}
          <div className="flex justify-center gap-6 mt-4">
            {slaData.map((entry, index) => (
              <div key={entry.name} className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: SLA_COLORS[index % SLA_COLORS.length] }} />
                <span className="text-sm font-medium text-foreground">{entry.name}:&nbsp;<strong>{entry.value}</strong></span>
              </div>
            ))}
          </div>
        </div>

        <div className="sn-card">
          <h3 className="text-lg font-bold mb-6">Ticket Status Distribution</h3>
          <div className="h-80 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" fontSize={12} />
                <YAxis fontSize={12} />
                <Tooltip 
                  contentStyle={{ backgroundColor: "#fff", borderRadius: "8px", border: "1px solid #e2e8f0" }}
                />
                <Bar dataKey="count" fill="#81B532" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="sn-card">
          <h3 className="text-lg font-bold mb-6">Tickets by Category</h3>
          <div className="h-80 w-full flex items-center justify-center">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={categoryData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={100}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {categoryData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="flex flex-wrap justify-center gap-4 mt-4">
            {categoryData.map((entry, index) => (
              <div key={entry.name} className="flex items-center gap-2">
                <div className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS[index % COLORS.length] }} />
                <span className="text-xs font-medium">{entry.name}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="sn-card lg:col-span-2">
          <h3 className="text-lg font-bold mb-6">Critical Incidents Map</h3>
          <div className="h-96 w-full bg-muted/30 rounded-lg border-2 border-dashed border-border flex flex-col items-center justify-center text-muted-foreground">
            <Map className="w-12 h-12 mb-4 opacity-20" />
            <p className="font-medium">Geospatial Incident Visualization</p>
            <p className="text-xs">Integration with Google Maps API pending configuration.</p>
          </div>
        </div>
      </div>
    </div>
  );
}

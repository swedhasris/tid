import React, { useState } from "react";
import { 
  GitPullRequest, 
  Search, 
  Filter, 
  Plus, 
  CheckCircle2, 
  Clock, 
  AlertCircle,
  Calendar,
  User,
  ChevronRight
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const MOCK_CHANGES = [
  { id: "CHG001", title: "Upgrade Core Switch Firmware", type: "Normal", state: "Authorize", risk: "High", requester: "Network Admin", plannedDate: "2026-04-20" },
  { id: "CHG002", title: "Patch Production Databases", type: "Standard", state: "Scheduled", risk: "Medium", requester: "DBA Lead", plannedDate: "2026-04-18" },
  { id: "CHG003", title: "Emergency Security Patch - Zero Day", type: "Emergency", state: "Implement", risk: "Critical", requester: "Security Officer", plannedDate: "2026-04-16" },
];

export function ChangeManagement() {
  const [changes] = useState(MOCK_CHANGES);

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <h1 className="text-3xl font-light text-sn-dark">Change Management</h1>
          <p className="text-muted-foreground">Control and manage system changes to minimize risk and impact.</p>
        </div>
        <Button className="bg-sn-green text-sn-dark font-bold gap-2">
          <Plus className="w-4 h-4" /> Create Change Request
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        {[
          { label: "New", count: 2, icon: Plus, color: "blue" },
          { label: "In Approval", count: 5, icon: Clock, color: "orange" },
          { label: "Scheduled", count: 8, icon: Calendar, color: "green" },
          { label: "Implemented", count: 124, icon: CheckCircle2, color: "gray" },
        ].map((stat) => (
          <div key={stat.label} className="sn-card p-4 flex items-center gap-4">
            <div className={cn(
              "w-10 h-10 rounded-lg flex items-center justify-center",
              stat.color === "blue" ? "bg-blue-100 text-blue-600" :
              stat.color === "orange" ? "bg-orange-100 text-orange-600" :
              stat.color === "green" ? "bg-green-100 text-green-600" :
              "bg-gray-100 text-gray-600"
            )}>
              <stat.icon className="w-5 h-5" />
            </div>
            <div>
              <div className="text-xl font-bold text-sn-dark">{stat.count}</div>
              <div className="text-[10px] font-bold uppercase text-muted-foreground">{stat.label}</div>
            </div>
          </div>
        ))}
      </div>

      <div className="sn-card p-0 overflow-hidden">
        <div className="p-4 border-b border-border bg-muted/10 flex items-center justify-between">
          <div className="flex items-center gap-2 flex-grow max-w-md">
            <div className="relative flex-grow">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input 
                type="text"
                placeholder="Search changes..."
                className="w-full bg-white border border-border rounded py-1.5 pl-9 pr-3 text-sm outline-none focus:ring-1 focus:ring-sn-green"
              />
            </div>
            <Button variant="outline" size="sm" className="gap-2 border-border">
              <Filter className="w-3.5 h-3.5" /> Filter
            </Button>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-muted/30 border-b border-border">
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">Number</th>
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">Short Description</th>
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">Type</th>
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">State</th>
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">Risk</th>
                <th className="p-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">Planned Start</th>
                <th className="p-4 w-10"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {changes.map((change) => (
                <tr key={change.id} className="hover:bg-muted/20 transition-colors group cursor-pointer">
                  <td className="p-4 text-sm font-mono text-blue-600 font-bold">{change.id}</td>
                  <td className="p-4 text-sm font-medium text-sn-dark">{change.title}</td>
                  <td className="p-4 text-sm text-muted-foreground">{change.type}</td>
                  <td className="p-4">
                    <span className="px-2 py-0.5 rounded bg-muted text-[10px] font-bold uppercase text-muted-foreground">
                      {change.state}
                    </span>
                  </td>
                  <td className="p-4">
                    <span className={cn(
                      "px-2 py-0.5 rounded text-[10px] font-bold uppercase",
                      change.risk === "Critical" || change.risk === "High" ? "bg-red-100 text-red-700" : "bg-blue-100 text-blue-700"
                    )}>
                      {change.risk}
                    </span>
                  </td>
                  <td className="p-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-2">
                      <Calendar className="w-3.5 h-3.5" /> {change.plannedDate}
                    </div>
                  </td>
                  <td className="p-4">
                    <ChevronRight className="w-4 h-4 text-muted-foreground group-hover:text-sn-green transition-all" />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

import { collection, getDocs, addDoc, query, limit, setDoc, doc } from "firebase/firestore";
import { db } from "./firebase";

export async function seedInitialData() {
  try {
    const slaRef = collection(db, "sla_policies");
    const snapshot = await getDocs(query(slaRef, limit(1)));
    
    if (snapshot.empty) {
      console.log("Seeding initial SLA policies...");
      const policies = [
        { name: "P1 - Critical", priority: "1 - Critical", responseTimeHours: 1, resolutionTimeHours: 4, isActive: true },
        { name: "P2 - High", priority: "2 - High", responseTimeHours: 2, resolutionTimeHours: 8, isActive: true },
        { name: "P3 - Moderate", priority: "3 - Moderate", responseTimeHours: 4, resolutionTimeHours: 24, isActive: true },
        { name: "P4 - Low", priority: "4 - Low", responseTimeHours: 8, resolutionTimeHours: 48, isActive: true },
        { name: "Standard Inquiry", priority: "4 - Low", category: "Inquiry / Help", responseTimeHours: 12, resolutionTimeHours: 72, isActive: true }
      ];
      
      for (const p of policies) {
        await addDoc(slaRef, p);
      }
      console.log("SLA policies seeded successfully.");
    }

    // Seed some initial dashboards if needed
    const dashRef = collection(db, "dashboards");
    const dashSnap = await getDocs(query(dashRef, limit(1)));
    if (dashSnap.empty) {
       console.log("Seeding initial dashboards data...");
       const dashboards = [
         { name: "Incident Management", description: "Standard incident tracking", active: true, views: 0 },
         { name: "Asset Overview", description: "IT Asset list", active: true, views: 0 }
       ];
       for (const d of dashboards) {
         await addDoc(dashRef, d);
       }
    }

  } catch (error) {
    console.warn("Seeding skipped or failed:", error);
  }
}

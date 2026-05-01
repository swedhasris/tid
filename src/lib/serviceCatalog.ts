import { useEffect, useState } from "react";
import { collection, onSnapshot, orderBy, query } from "firebase/firestore";
import { db } from "./firebase";

export type CategoryItem = {
  id: string;
  name: string;
  isActive?: boolean;
};

export type SubcategoryItem = {
  id: string;
  name: string;
  categoryId: string;
  categoryName?: string;
  isActive?: boolean;
};

export type ServiceItem = {
  id: string;
  name: string;
  providerName: string;
  categoryId: string;
  categoryName?: string;
  subcategoryId: string;
  subcategoryName?: string;
  isActive?: boolean;
};

export type GroupItem = {
  id: string;
  name: string;
  members?: string[];
  categoryIds?: string[];
  subcategoryIds?: string[];
  serviceIds?: string[];
  isActive?: boolean;
};

export function useServiceCatalog() {
  const [categories, setCategories] = useState<CategoryItem[]>([]);
  const [subcategories, setSubcategories] = useState<SubcategoryItem[]>([]);
  const [services, setServices] = useState<ServiceItem[]>([]);
  const [groups, setGroups] = useState<GroupItem[]>([]);

  useEffect(() => {
    const unsubs = [
      onSnapshot(query(collection(db, "settings_categories"), orderBy("createdAt", "asc")), (snap) => {
        setCategories(
          snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<CategoryItem, "id">) }))
            .filter((item) => item.isActive !== false)
        );
      }),
      onSnapshot(query(collection(db, "settings_subcategories"), orderBy("createdAt", "asc")), (snap) => {
        setSubcategories(
          snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<SubcategoryItem, "id">) }))
            .filter((item) => item.isActive !== false)
        );
      }),
      onSnapshot(query(collection(db, "settings_services"), orderBy("createdAt", "asc")), (snap) => {
        setServices(
          snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<ServiceItem, "id">) }))
            .filter((item) => item.isActive !== false)
        );
      }),
      onSnapshot(query(collection(db, "settings_groups"), orderBy("createdAt", "asc")), (snap) => {
        setGroups(
          snap.docs.map((d) => ({ id: d.id, ...(d.data() as Omit<GroupItem, "id">) }))
            .filter((item) => item.isActive !== false)
        );
      }),
    ];

    return () => {
      unsubs.forEach((unsubscribe) => unsubscribe());
    };
  }, []);

  return { categories, subcategories, services, groups };
}

export function getSubcategoriesForCategory(subcategories: SubcategoryItem[], categoryId: string) {
  return subcategories.filter((item) => item.categoryId === categoryId);
}

export function getServicesForSubcategory(services: ServiceItem[], subcategoryId: string) {
  return services.filter((item) => item.subcategoryId === subcategoryId);
}

export function getGroupsForSelection(groups: GroupItem[], categoryId: string, subcategoryId: string, serviceId: string) {
  return groups.filter((group) => {
    const categoryMatch = !group.categoryIds?.length || group.categoryIds.includes(categoryId);
    const subcategoryMatch = !group.subcategoryIds?.length || group.subcategoryIds.includes(subcategoryId);
    const serviceMatch = !group.serviceIds?.length || group.serviceIds.includes(serviceId);
    return categoryMatch && subcategoryMatch && serviceMatch;
  });
}

export function serviceDisplayName(service: Pick<ServiceItem, "name" | "providerName">) {
  return service.providerName ? `${service.name} - ${service.providerName}` : service.name;
}

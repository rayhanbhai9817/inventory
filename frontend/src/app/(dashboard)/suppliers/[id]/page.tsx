import { SupplierDetailView } from "./SupplierDetailView";

export default async function SupplierDetailPage(props: PageProps<"/suppliers/[id]">) {
  const { id } = await props.params;

  return <SupplierDetailView id={Number(id)} />;
}

import { SupplierEditForm } from "./SupplierEditForm";

export default async function EditSupplierPage(props: PageProps<"/suppliers/[id]/edit">) {
  const { id } = await props.params;

  return <SupplierEditForm id={Number(id)} />;
}
